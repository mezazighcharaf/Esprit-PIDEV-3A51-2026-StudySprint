# StudySprint – Collaborative Learning Platform

## Overview
StudySprint is a collaborative learning platform developed with Symfony 6.4.  
It helps students organize their study sessions, generate quizzes and flashcards, manage revision plans, and collaborate through study groups.

This project was developed as part of the **PIDEV – 3rd Year Engineering Program** at **Esprit School of Engineering – Tunisia** (Academic Year 2025–2026).

## Features

- User authentication and profile management
- Subjects and chapters management
- Study planning and task scheduling
- Quiz system with scoring and attempt history
- Flashcards with spaced repetition review
- Study groups with posts and comments
- Notifications and leaderboard
- AI-assisted quiz and flashcard generation
- Back-office administration dashboard

## Tech Stack

### Backend
- PHP 8.1+
- Symfony 6.4
- Doctrine ORM
- Symfony Security

### Frontend
- Twig
- HTML / CSS / JavaScript
- Chart.js
- FullCalendar

### Database
- MySQL / MariaDB

### Additional Tools
- Mercure (realtime updates)
- Dompdf (PDF generation)
- Endroid QR Code
- LiipImagineBundle (image processing)

### AI Services
- FastAPI microservice
- Ollama integration
- AI gateway for quiz and flashcard generation

## Architecture

The application follows the Symfony MVC architecture:

- **Controllers** manage HTTP requests
- **Entities** represent the domain model
- **Repositories** handle data access
- **Services** contain business logic
- **Twig templates** render the frontend

A separate Python AI microservice provides AI-based learning features.

## Contributors

- Charaf Mezazigh
- StudySprint Team

## Academic Context

Developed at **Esprit School of Engineering – Tunisia**

PIDEV – 3rd Year Engineering Program  
Academic Year **2025–2026**

## Getting Started

### Requirements

- PHP >= 8.1
- Composer
- MySQL / MariaDB
- Symfony CLI

### Installation

```bash
git clone https://github.com/mezazighcharaf/StudySprint.git
cd StudySprint
composer install
