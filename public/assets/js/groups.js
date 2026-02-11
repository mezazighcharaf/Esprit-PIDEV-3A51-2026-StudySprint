/* ============================================
   STUDYSPRINT — GROUPS & POSTS
   AJAX interactions for Study Groups
   ============================================ */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const groupContainer = document.querySelector('.group-detail-container');
        const groupsListPage = document.getElementById('my-groups-container');

        if (!groupContainer && !groupsListPage) return;

        // ─── POST CREATION (Detail Page) ───
        if (groupContainer) {
            const groupId = groupContainer.dataset.groupId;
            const newPostForm = document.getElementById('new-post-form');

            if (newPostForm) {
                const postTypeBtns = document.querySelectorAll('.post-type-btn');
                const attachmentField = document.getElementById('attachment-field');
                let currentPostType = 'note';

                postTypeBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        postTypeBtns.forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        currentPostType = btn.dataset.type;

                        if (currentPostType === 'resource' || currentPostType === 'assignment') {
                            attachmentField.classList.remove('d-none');
                        } else {
                            attachmentField.classList.add('d-none');
                        }
                    });
                });

                newPostForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const title = document.getElementById('post-title').value;
                    const body = document.getElementById('post-body').value;
                    const attachmentUrl = document.getElementById('post-attachment')?.value;
                    const csrfToken = document.querySelector(`meta[name="csrf-token-create-post-${groupId}"]`)?.content;

                    const formData = new FormData();
                    formData.append('title', title);
                    formData.append('body', body);
                    formData.append('post_type', currentPostType);
                    if (attachmentUrl) formData.append('attachment_url', attachmentUrl);
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch(`/app/groupes/${groupId}/posts/create`, {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();
                        if (data.success) {
                            window.StudySprint.Toast.success('Succès', 'Votre post a été publié.');
                            newPostForm.reset();
                            location.reload();
                        } else {
                            window.StudySprint.Toast.error('Erreur', data.error || 'Impossible de publier le post.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        window.StudySprint.Toast.error('Erreur', 'Une erreur est survenue.');
                    }
                });
            }

            // ─── POST INTERACTIONS (Detail Page) ───
            document.addEventListener('click', async (e) => {
                // Like
                const likeBtn = e.target.closest('[data-like-post]');
                if (likeBtn) {
                    const postId = likeBtn.dataset.likePost;
                    const csrfToken = document.querySelector(`meta[name="csrf-token-like-post-${postId}"]`)?.content;

                    const formData = new FormData();
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch(`/app/posts/${postId}/like`, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        if (data.success) {
                            const span = likeBtn.querySelector('span');
                            const svg = likeBtn.querySelector('svg');
                            span.textContent = data.likesCount;
                            likeBtn.classList.toggle('active', data.liked);
                            svg.setAttribute('fill', data.liked ? 'currentColor' : 'none');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                    }
                    return;
                }

                // Delete Post
                const deleteBtn = e.target.closest('[data-post-delete]');
                if (deleteBtn) {
                    if (!confirm('Voulez-vous vraiment supprimer ce post ?')) return;

                    const postId = deleteBtn.dataset.postDelete;
                    const csrfToken = document.querySelector(`meta[name="csrf-token-delete-post-${postId}"]`)?.content;

                    const formData = new FormData();
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch(`/app/groupes/${groupId}/posts/${postId}/delete`, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        if (data.success) {
                            const postCard = document.querySelector(`.post-card[data-post-id="${postId}"]`);
                            postCard?.remove();
                            window.StudySprint.Toast.success('Supprimé', 'Le post a été supprimé.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                    }
                    return;
                }

                // Open Comments Drawer
                const viewCommentsBtn = e.target.closest('[data-view-comments]');
                if (viewCommentsBtn) {
                    const postId = viewCommentsBtn.dataset.viewComments;
                    loadComments(postId);
                    return;
                }

                // Share Modal
                const shareBtn = e.target.closest('[data-share-post]');
                if (shareBtn) {
                    const postId = shareBtn.dataset.sharePost;
                    activePostId = postId;
                    document.querySelector('[data-share-modal]')?.classList.add('open');
                    document.querySelector('[data-share-modal-backdrop]')?.classList.add('open');
                    return;
                }
            });

            // Share Modal Close
            document.querySelector('[data-share-close]')?.addEventListener('click', () => {
                document.querySelector('[data-share-modal]')?.classList.remove('open');
                document.querySelector('[data-share-modal-backdrop]')?.classList.remove('open');
            });

            document.querySelector('[data-share-modal-backdrop]')?.addEventListener('click', () => {
                document.querySelector('[data-share-modal]')?.classList.remove('open');
                document.querySelector('[data-share-modal-backdrop]')?.classList.remove('open');
            });

            // Share Actions
            document.querySelector('[data-share-copy]')?.addEventListener('click', () => {
                const url = `${window.location.origin}/app/posts/${activePostId}`;
                navigator.clipboard.writeText(url).then(() => {
                    window.StudySprint.Toast.success('Copié', 'Lien copié dans le presse-papiers.');
                });
            });

            // Rating logic
            document.addEventListener('mouseover', (e) => {
                const star = e.target.closest('.user-rating-stars .star-btn');
                if (star) {
                    const container = star.closest('.user-rating-stars');
                    const value = parseInt(star.dataset.value);
                    const stars = container.querySelectorAll('.star-btn');
                    stars.forEach((s, idx) => {
                        s.setAttribute('fill', idx < value ? 'currentColor' : 'none');
                    });
                }
            });

            document.addEventListener('mouseout', (e) => {
                const container = e.target.closest('.user-rating-stars');
                if (container) {
                    const currentRating = parseInt(container.dataset.currentRating || 0);
                    const stars = container.querySelectorAll('.star-btn');
                    stars.forEach((s, idx) => {
                        s.setAttribute('fill', idx < currentRating ? 'currentColor' : 'none');
                    });
                }
            });

            document.addEventListener('click', async (e) => {
                const star = e.target.closest('.user-rating-stars .star-btn');
                if (star) {
                    const container = star.closest('.user-rating-stars');
                    const postId = container.dataset.postRating;
                    const value = star.dataset.value;
                    const csrfToken = document.querySelector(`meta[name="csrf-token-rate-post-${postId}"]`)?.content;

                    const formData = new FormData();
                    formData.append('rating', value);
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch(`/app/posts/${postId}/rate`, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        if (data.success) {
                            container.dataset.currentRating = data.userRating;
                            const postCard = container.closest('.post-card');
                            const avgDisplay = postCard.querySelector('.rating-value');
                            if (avgDisplay) avgDisplay.textContent = parseFloat(data.averageRating).toFixed(1);
                            window.StudySprint.Toast.info('Note', 'Votre note a été enregistrée.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                    }
                }
            });

            // ─── COMMENTS LOGIC (Detail Page) ───
            const commentsDrawer = document.querySelector('[data-comments-drawer]');
            const commentsList = document.querySelector('[data-comments-list]');
            const commentInput = document.querySelector('[data-new-comment-input]');
            const submitCommentBtn = document.querySelector('[data-post-comment]');
            let activePostId = null;

            async function loadComments(postId) {
                activePostId = postId;
                commentsList.innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';

                commentsDrawer.classList.add('open');
                document.querySelector('.drawer-backdrop').classList.add('open');

                try {
                    const response = await fetch(`/app/posts/${postId}/comments`);
                    const data = await response.json();
                    if (data.success) renderComments(data.comments);
                } catch (error) {
                    commentsList.innerHTML = '<div class="text-danger p-4">Erreur de chargement.</div>';
                }
            }

            function renderComments(comments) {
                if (comments.length === 0) {
                    commentsList.innerHTML = '<div class="text-center p-4 text-muted">Aucun commentaire. Soyez le premier !</div>';
                    return;
                }
                commentsList.innerHTML = comments.map(comment => `
                    <div class="comment-item mb-3" data-comment-id="${comment.id}">
                        <div class="d-flex gap-2">
                            <div class="avatar avatar-xs avatar-initials">${comment.author.initials}</div>
                            <div class="comment-content-wrap flex-1">
                                <div class="comment-bubble">
                                    <div class="comment-author-name">${comment.author.name}</div>
                                    <div class="comment-body-text">${comment.body}</div>
                                </div>
                                <div class="comment-meta mt-1">
                                    <span class="text-xs text-muted">${comment.timeAgo}</span>
                                    ${comment.canDelete ? `<button class="btn-link text-xs text-danger ms-2" data-delete-comment="${comment.id}">Supprimer</button>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            submitCommentBtn?.addEventListener('click', async () => {
                const body = commentInput.value.trim();
                if (!body || !activePostId) return;
                const csrfToken = document.querySelector(`meta[name="csrf-token-comment-post-${activePostId}"]`)?.content;
                const formData = new FormData();
                formData.append('body', body);
                formData.append('_token', csrfToken);
                submitCommentBtn.disabled = true;
                try {
                    const response = await fetch(`/app/posts/${activePostId}/comments/create`, { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.success) {
                        commentInput.value = '';
                        loadComments(activePostId);
                        const countSpan = document.querySelector(`.post-card[data-post-id="${activePostId}"] [data-view-comments] span`);
                        if (countSpan) countSpan.textContent = parseInt(countSpan.textContent) + 1;
                    }
                } catch (error) {
                    window.StudySprint.Toast.error('Erreur', 'Impossible de publier le commentaire.');
                } finally {
                    submitCommentBtn.disabled = false;
                }
            });

            document.querySelector('[data-comments-close]')?.addEventListener('click', () => {
                commentsDrawer.classList.remove('open');
                document.querySelector('.drawer-backdrop').classList.remove('open');
            });

            commentsList?.addEventListener('click', async (e) => {
                const delBtn = e.target.closest('[data-delete-comment]');
                if (delBtn) {
                    if (!confirm('Supprimer ce commentaire ?')) return;
                    const commentId = delBtn.dataset.deleteComment;
                    const csrfToken = document.querySelector(`meta[name="csrf-token-comment-post-${activePostId}"]`)?.content;
                    const formData = new FormData();
                    formData.append('_token', csrfToken);
                    try {
                        await fetch(`/app/comments/${commentId}/delete`, { method: 'POST', body: formData });
                        loadComments(activePostId);
                        const countSpan = document.querySelector(`.post-card[data-post-id="${activePostId}"] [data-view-comments] span`);
                        if (countSpan) countSpan.textContent = Math.max(0, parseInt(countSpan.textContent) - 1);
                    } catch (error) { console.error('Error deleting comment:', error); }
                }
            });
        }

        // ─── LEAVE / DELETE GROUP (List & Detail) ───
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.leave-group-btn');
            if (!btn) return;
            const groupId = btn.dataset.groupId;
            const groupName = btn.dataset.groupName;
            const action = btn.dataset.action;
            const csrfToken = btn.dataset.csrf;
            const confirmMsg = action === 'delete'
                ? `Êtes-vous sûr de vouloir SUPPRIMER le groupe "${groupName}" ?`
                : `Voulez-vous vraiment quitter le groupe "${groupName}" ?`;
            if (!confirm(confirmMsg)) return;
            const formData = new FormData();
            formData.append('_token', csrfToken);
            const url = action === 'delete' ? `/app/groupes/${groupId}/delete` : `/app/groupes/${groupId}/leave`;
            try {
                const response = await fetch(url, { method: 'POST', body: formData });
                if (response.redirected) { window.location.href = response.url; }
                else {
                    const data = await response.json().catch(() => ({}));
                    if (data.success || response.ok) {
                        window.StudySprint.Toast.success('Succès', action === 'delete' ? 'Groupe supprimé.' : 'Quitté.');
                        window.location.href = '/app/groupes';
                    } else { window.StudySprint.Toast.error('Erreur', data.error || 'Erreur.'); }
                }
            } catch (error) { window.StudySprint.Toast.error('Erreur', 'Erreur.'); }
        });

        // ─── JOIN GROUP BY CODE ───
        const joinCodeForm = document.querySelector('#join-group-modal form[action$="/code"]');
        joinCodeForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(joinCodeForm);
            try {
                const response = await fetch(joinCodeForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (data.success) {
                    window.StudySprint.Toast.success('Succès', 'Vous avez rejoint le groupe !');
                    setTimeout(() => window.location.href = data.redirect || window.location.href, 1000);
                } else {
                    window.StudySprint.Toast.error('Erreur', data.error || 'Code invalide.');
                }
            } catch (error) {
                window.StudySprint.Toast.error('Erreur', 'Une erreur est survenue.');
            }
        });

        // ─── JOIN PUBLIC GROUP ───
        document.addEventListener('submit', async (e) => {
            const form = e.target.closest('form[action*="/join-public/"]');
            if (!form) return;
            e.preventDefault();
            const formData = new FormData(form);
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (data.success) {
                    window.StudySprint.Toast.success('Succès', 'Bienvenue dans le groupe !');
                    setTimeout(() => window.location.href = data.redirect || window.location.href, 1000);
                } else {
                    window.StudySprint.Toast.error('Erreur', data.error || 'Impossible de rejoindre.');
                }
            } catch (error) {
                window.StudySprint.Toast.error('Erreur', 'Une erreur est survenue.');
            }
        });

        // ─── SEARCH & FILTER ───
        const searchInput = document.getElementById('group-search-input');
        const roleFilter = document.getElementById('group-filter-role');
        if (searchInput || roleFilter) {
            const filterGroups = () => {
                const query = searchInput?.value.toLowerCase() || '';
                const role = roleFilter?.value || 'all';
                document.querySelectorAll('[data-group-card]').forEach(card => {
                    const matchesSearch = (card.dataset.name || '').includes(query) || (card.dataset.subject || '').includes(query);
                    const matchesRole = role === 'all' || card.dataset.role === role;
                    card.style.display = (matchesSearch && matchesRole) ? '' : 'none';
                });
            };
            searchInput?.addEventListener('input', filterGroups);
            roleFilter?.addEventListener('change', filterGroups);
        }

        const publicSearchInput = document.getElementById('public-group-search');
        if (publicSearchInput) {
            publicSearchInput.addEventListener('input', () => {
                const query = publicSearchInput.value.toLowerCase();
                let hasResults = false;
                document.querySelectorAll('[data-public-group-item]').forEach(item => {
                    if ((item.dataset.searchContent || '').includes(query)) {
                        item.style.display = ''; hasResults = true;
                    } else { item.style.display = 'none'; }
                });
                const noResults = document.getElementById('public-groups-no-results');
                if (noResults) noResults.style.display = hasResults ? 'none' : 'block';
            });
        }
    });
})();
