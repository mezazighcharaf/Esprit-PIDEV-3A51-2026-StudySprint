"""
Plan Generator Service
Exact port from Symfony PlanGeneratorService
"""
from datetime import datetime, date, timedelta
from typing import Optional, List
from sqlalchemy.orm import Session

from app.models.planning import RevisionPlan, PlanTask
from app.models.subject import Subject
from app.models.user import User


class PlanGeneratorService:
    """
    Generate revision plans automatically.

    Distributes study sessions across available days,
    alternating between task types.
    """

    # Session start times (24h format)
    SESSION_HOURS = [9, 14, 17, 19]

    # Task types to rotate through
    TASK_TYPES = ["REVISION", "QUIZ", "FLASHCARD"]

    def find_overlapping_plan(
        self,
        db: Session,
        user: User,
        subject: Subject,
        start_date: date,
        end_date: date
    ) -> Optional[RevisionPlan]:
        """
        Find an existing plan that overlaps with the given date range.

        Args:
            db: Database session
            user: The user
            subject: The subject
            start_date: Start date
            end_date: End date

        Returns:
            Overlapping plan if found, None otherwise
        """
        return db.query(RevisionPlan).filter(
            RevisionPlan.user_id == user.id,
            RevisionPlan.subject_id == subject.id,
            RevisionPlan.start_date <= end_date,
            RevisionPlan.end_date >= start_date
        ).first()

    def generate_plan(
        self,
        db: Session,
        user: User,
        subject: Subject,
        start_date: date,
        end_date: date,
        sessions_per_day: int = 2,
        skip_weekends: bool = False
    ) -> RevisionPlan:
        """
        Generate a new revision plan with tasks.

        Args:
            db: Database session
            user: The user
            subject: The subject to study
            start_date: Plan start date
            end_date: Plan end date
            sessions_per_day: Number of sessions per day (1-5)
            skip_weekends: Whether to skip Saturdays and Sundays

        Returns:
            Created revision plan with tasks
        """
        # Create the plan
        plan = RevisionPlan()
        plan.user_id = user.id
        plan.subject_id = subject.id
        plan.title = f"Plan auto - {subject.name}"
        plan.start_date = start_date
        plan.end_date = end_date
        plan.status = "ACTIVE"
        plan.generated_by_ai = True
        plan.ai_meta = {
            "sessions_per_day": sessions_per_day,
            "skip_weekends": skip_weekends,
            "generated_at": datetime.utcnow().isoformat()
        }

        db.add(plan)
        db.flush()  # Get plan ID

        # Generate tasks
        tasks = self._generate_tasks(
            plan=plan,
            subject=subject,
            start_date=start_date,
            end_date=end_date,
            sessions_per_day=sessions_per_day,
            skip_weekends=skip_weekends
        )

        for task in tasks:
            db.add(task)

        return plan

    def replace_plan(
        self,
        db: Session,
        existing_plan: RevisionPlan,
        new_start_date: date,
        new_end_date: date,
        sessions_per_day: int = 2,
        skip_weekends: bool = False
    ) -> RevisionPlan:
        """
        Replace an existing plan with new parameters.

        Deletes old tasks and generates new ones.

        Args:
            db: Database session
            existing_plan: The plan to replace
            new_start_date: New start date
            new_end_date: New end date
            sessions_per_day: Sessions per day
            skip_weekends: Skip weekends

        Returns:
            Updated plan
        """
        # Delete existing tasks
        db.query(PlanTask).filter(PlanTask.plan_id == existing_plan.id).delete()

        # Update plan
        existing_plan.start_date = new_start_date
        existing_plan.end_date = new_end_date
        existing_plan.ai_meta = {
            "sessions_per_day": sessions_per_day,
            "skip_weekends": skip_weekends,
            "generated_at": datetime.utcnow().isoformat(),
            "replaced": True
        }

        # Get subject for task titles
        subject = db.query(Subject).filter(Subject.id == existing_plan.subject_id).first()

        # Generate new tasks
        tasks = self._generate_tasks(
            plan=existing_plan,
            subject=subject,
            start_date=new_start_date,
            end_date=new_end_date,
            sessions_per_day=sessions_per_day,
            skip_weekends=skip_weekends
        )

        for task in tasks:
            db.add(task)

        return existing_plan

    def _generate_tasks(
        self,
        plan: RevisionPlan,
        subject: Subject,
        start_date: date,
        end_date: date,
        sessions_per_day: int,
        skip_weekends: bool
    ) -> List[PlanTask]:
        """Generate task list for a plan."""
        tasks = []
        current_date = start_date
        task_type_index = 0
        session_hours = self.SESSION_HOURS[:sessions_per_day]

        while current_date <= end_date:
            # Skip weekends if configured
            if skip_weekends and current_date.weekday() >= 5:
                current_date += timedelta(days=1)
                continue

            for hour in session_hours:
                task_type = self.TASK_TYPES[task_type_index % len(self.TASK_TYPES)]
                task_type_index += 1

                # Create task
                task = PlanTask()
                task.plan_id = plan.id
                task.title = self._generate_task_title(subject, task_type)
                task.task_type = task_type
                task.start_at = datetime.combine(current_date, datetime.min.time().replace(hour=hour))
                task.end_at = task.start_at + timedelta(hours=1)
                task.status = "TODO"
                task.priority = 1 if task_type == "REVISION" else 2

                tasks.append(task)

            current_date += timedelta(days=1)

        return tasks

    def _generate_task_title(self, subject: Subject, task_type: str) -> str:
        """Generate a title for a task."""
        subject_name = subject.name if subject else "Matière"

        titles = {
            "REVISION": f"Réviser {subject_name}",
            "QUIZ": f"Quiz - {subject_name}",
            "FLASHCARD": f"Flashcards - {subject_name}",
            "CUSTOM": f"Session - {subject_name}"
        }

        return titles.get(task_type, f"Étude - {subject_name}")


# Singleton instance
plan_generator_service = PlanGeneratorService()
