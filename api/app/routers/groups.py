"""
Groups and Posts Router
"""
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from typing import Optional

from app.database import get_db
from app.models.user import User
from app.models.group import StudyGroup, GroupMember, GroupPost
from app.schemas.group import (
    GroupCreate, GroupUpdate, GroupResponse, GroupDetailResponse,
    PostCreate, PostResponse, PostWithCommentsResponse, CommentCreate,
    MemberResponse, AuthorInfo
)
from app.schemas.common import PaginatedResponse, PaginationMeta
from app.dependencies import get_current_user

router = APIRouter(prefix="/groups", tags=["Groups"])


def get_user_role_in_group(db: Session, group_id: int, user_id: int) -> Optional[str]:
    """Get user's role in a group."""
    member = db.query(GroupMember).filter(
        GroupMember.group_id == group_id,
        GroupMember.user_id == user_id
    ).first()
    return member.member_role if member else None


def can_moderate(db: Session, group: StudyGroup, user: User) -> bool:
    """Check if user can moderate the group."""
    if user.is_admin():
        return True
    role = get_user_role_in_group(db, group.id, user.id)
    return role in ["owner", "admin"]


@router.get("", response_model=PaginatedResponse[GroupResponse])
async def list_groups(
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=100),
    my_groups: bool = Query(False, description="Only show groups I'm member of"),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    List groups (public + user's groups).
    """
    if my_groups:
        query = db.query(StudyGroup).join(GroupMember).filter(
            GroupMember.user_id == current_user.id
        )
    else:
        # Public groups + user's private groups
        member_group_ids = db.query(GroupMember.group_id).filter(
            GroupMember.user_id == current_user.id
        ).subquery()

        query = db.query(StudyGroup).filter(
            (StudyGroup.privacy == "PUBLIC") |
            (StudyGroup.id.in_(member_group_ids))
        )

    total = query.count()
    groups = query.order_by(StudyGroup.created_at.desc()).offset((page - 1) * limit).limit(limit).all()

    data = []
    for g in groups:
        resp = GroupResponse.model_validate(g)
        resp.members_count = len(g.members)
        resp.posts_count = len([p for p in g.posts if p.post_type == "POST"])
        data.append(resp)

    total_pages = (total + limit - 1) // limit

    return PaginatedResponse(
        data=data,
        pagination=PaginationMeta(
            current_page=page,
            total_pages=total_pages,
            total_items=total,
            items_per_page=limit,
            has_next=page < total_pages,
            has_prev=page > 1
        )
    )


@router.get("/{group_id}", response_model=GroupDetailResponse)
async def get_group(
    group_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get group details.
    """
    group = db.query(StudyGroup).filter(StudyGroup.id == group_id).first()
    if not group:
        raise HTTPException(status_code=404, detail="Group not found")

    user_role = get_user_role_in_group(db, group_id, current_user.id)

    # Check access for private groups
    if group.privacy == "PRIVATE" and not user_role and not current_user.is_admin():
        raise HTTPException(status_code=403, detail="This is a private group")

    response = GroupDetailResponse.model_validate(group)
    response.members_count = len(group.members)
    response.posts_count = len([p for p in group.posts if p.post_type == "POST"])
    response.is_member = user_role is not None
    response.user_role = user_role

    return response


@router.post("", response_model=GroupResponse, status_code=201)
async def create_group(
    request: GroupCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Create a new group.
    """
    group = StudyGroup()
    group.name = request.name
    group.description = request.description
    group.privacy = request.privacy.value
    group.created_by_id = current_user.id

    db.add(group)
    db.flush()

    # Add creator as owner
    member = GroupMember()
    member.group_id = group.id
    member.user_id = current_user.id
    member.member_role = "owner"
    db.add(member)

    db.commit()
    db.refresh(group)

    return GroupResponse.model_validate(group)


@router.put("/{group_id}", response_model=GroupResponse)
async def update_group(
    group_id: int,
    request: GroupUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Update a group (owner/admin only).
    """
    group = db.query(StudyGroup).filter(StudyGroup.id == group_id).first()
    if not group:
        raise HTTPException(status_code=404, detail="Group not found")

    if not can_moderate(db, group, current_user):
        raise HTTPException(status_code=403, detail="Not authorized")

    if request.name is not None:
        group.name = request.name
    if request.description is not None:
        group.description = request.description
    if request.privacy is not None:
        group.privacy = request.privacy.value

    db.commit()
    db.refresh(group)

    return GroupResponse.model_validate(group)


@router.delete("/{group_id}", status_code=204)
async def delete_group(
    group_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Delete a group (owner only).
    """
    group = db.query(StudyGroup).filter(StudyGroup.id == group_id).first()
    if not group:
        raise HTTPException(status_code=404, detail="Group not found")

    role = get_user_role_in_group(db, group_id, current_user.id)
    if role != "owner" and not current_user.is_admin():
        raise HTTPException(status_code=403, detail="Only owner can delete group")

    db.delete(group)
    db.commit()


# Posts
@router.get("/{group_id}/posts", response_model=PaginatedResponse[PostWithCommentsResponse])
async def list_posts(
    group_id: int,
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=50),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get group feed (posts with comments).
    """
    group = db.query(StudyGroup).filter(StudyGroup.id == group_id).first()
    if not group:
        raise HTTPException(status_code=404, detail="Group not found")

    user_role = get_user_role_in_group(db, group_id, current_user.id)
    if group.privacy == "PRIVATE" and not user_role and not current_user.is_admin():
        raise HTTPException(status_code=403, detail="Not a member")

    query = db.query(GroupPost).filter(
        GroupPost.group_id == group_id,
        GroupPost.post_type == "POST"
    )

    total = query.count()
    posts = query.order_by(GroupPost.created_at.desc()).offset((page - 1) * limit).limit(limit).all()

    data = []
    for post in posts:
        author = db.query(User).filter(User.id == post.author_id).first()
        comments = db.query(GroupPost).filter(GroupPost.parent_post_id == post.id).all()

        post_resp = PostWithCommentsResponse.model_validate(post)
        post_resp.author = AuthorInfo(id=author.id, full_name=author.full_name) if author else None
        post_resp.comments_count = len(comments)
        post_resp.comments = [PostResponse.model_validate(c) for c in comments]
        data.append(post_resp)

    total_pages = (total + limit - 1) // limit

    return PaginatedResponse(
        data=data,
        pagination=PaginationMeta(
            current_page=page,
            total_pages=total_pages,
            total_items=total,
            items_per_page=limit,
            has_next=page < total_pages,
            has_prev=page > 1
        )
    )


@router.post("/{group_id}/posts", response_model=PostResponse, status_code=201)
async def create_post(
    group_id: int,
    request: PostCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Create a post in a group.
    """
    group = db.query(StudyGroup).filter(StudyGroup.id == group_id).first()
    if not group:
        raise HTTPException(status_code=404, detail="Group not found")

    user_role = get_user_role_in_group(db, group_id, current_user.id)
    if not user_role and group.privacy == "PRIVATE":
        raise HTTPException(status_code=403, detail="Not a member")

    post = GroupPost()
    post.group_id = group_id
    post.author_id = current_user.id
    post.post_type = "POST"
    post.title = request.title
    post.body = request.body

    db.add(post)
    db.commit()
    db.refresh(post)

    return PostResponse.model_validate(post)


# Posts router (without group prefix)
posts_router = APIRouter(prefix="/posts", tags=["Posts"])


@posts_router.post("/{post_id}/comments", response_model=PostResponse, status_code=201)
async def create_comment(
    post_id: int,
    request: CommentCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Add a comment to a post.
    """
    parent = db.query(GroupPost).filter(GroupPost.id == post_id).first()
    if not parent:
        raise HTTPException(status_code=404, detail="Post not found")

    group = db.query(StudyGroup).filter(StudyGroup.id == parent.group_id).first()
    user_role = get_user_role_in_group(db, parent.group_id, current_user.id)

    if group.privacy == "PRIVATE" and not user_role:
        raise HTTPException(status_code=403, detail="Not a member")

    comment = GroupPost()
    comment.group_id = parent.group_id
    comment.author_id = current_user.id
    comment.parent_post_id = post_id
    comment.post_type = "COMMENT"
    comment.body = request.body

    db.add(comment)
    db.commit()
    db.refresh(comment)

    return PostResponse.model_validate(comment)


@posts_router.delete("/{post_id}", status_code=204)
async def delete_post(
    post_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Delete a post (author or moderator).
    """
    post = db.query(GroupPost).filter(GroupPost.id == post_id).first()
    if not post:
        raise HTTPException(status_code=404, detail="Post not found")

    group = db.query(StudyGroup).filter(StudyGroup.id == post.group_id).first()

    # Check permission: author or moderator
    is_author = post.author_id == current_user.id
    is_mod = can_moderate(db, group, current_user)

    if not is_author and not is_mod:
        raise HTTPException(status_code=403, detail="Not authorized to delete this post")

    db.delete(post)
    db.commit()


# Join/Leave
@router.post("/{group_id}/join", response_model=MemberResponse, status_code=201)
async def join_group(
    group_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Join a public group.
    """
    group = db.query(StudyGroup).filter(StudyGroup.id == group_id).first()
    if not group:
        raise HTTPException(status_code=404, detail="Group not found")

    if group.privacy == "PRIVATE":
        raise HTTPException(status_code=403, detail="Cannot join private group")

    existing = db.query(GroupMember).filter(
        GroupMember.group_id == group_id,
        GroupMember.user_id == current_user.id
    ).first()
    if existing:
        raise HTTPException(status_code=409, detail="Already a member")

    member = GroupMember()
    member.group_id = group_id
    member.user_id = current_user.id
    member.member_role = "member"

    db.add(member)
    db.commit()
    db.refresh(member)

    return MemberResponse.model_validate(member)


@router.post("/{group_id}/leave", status_code=204)
async def leave_group(
    group_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Leave a group.
    """
    member = db.query(GroupMember).filter(
        GroupMember.group_id == group_id,
        GroupMember.user_id == current_user.id
    ).first()

    if not member:
        raise HTTPException(status_code=404, detail="Not a member")

    if member.member_role == "owner":
        raise HTTPException(status_code=400, detail="Owner cannot leave. Transfer ownership first.")

    db.delete(member)
    db.commit()
