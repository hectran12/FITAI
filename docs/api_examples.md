# FitAI API Examples

This document provides examples of all API endpoints with request/response formats.

## Table of Contents

- [Authentication](#authentication)
- [Profile](#profile)
- [Plans](#plans)
- [Logs](#logs)
- [Dashboard](#dashboard)
- [Exercises](#exercises)

---

## Authentication

### Register New User

**POST** `/api/auth/register.php`

```bash
curl -X POST http://localhost:8000/api/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "password_confirm": "password123"
  }'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Registration successful",
  "user": {
    "id": 2,
    "email": "user@example.com"
  },
  "csrf_token": "a1b2c3d4e5f6..."
}
```

**Error Response (400 Bad Request):**
```json
{
  "error": true,
  "message": "Email already registered"
}
```

---

### Login

**POST** `/api/auth/login.php`

```bash
curl -X POST http://localhost:8000/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@fitai.com",
    "password": "test123"
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 1,
    "email": "test@fitai.com"
  },
  "profile_complete": true,
  "csrf_token": "a1b2c3d4e5f6..."
}
```

---

### Logout

**POST** `/api/auth/logout.php`

```bash
curl -X POST http://localhost:8000/api/auth/logout.php \
  -H "Content-Type: application/json" \
  -H "Cookie: fitai_session=your_session_cookie"
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### Check Session

**GET** `/api/auth/session.php`

```bash
curl http://localhost:8000/api/auth/session.php \
  -H "Cookie: fitai_session=your_session_cookie"
```

**Response (Authenticated):**
```json
{
  "authenticated": true,
  "user": {
    "id": 1,
    "email": "test@fitai.com",
    "created_at": "2025-01-08 12:00:00"
  },
  "profile_complete": true,
  "csrf_token": "a1b2c3d4e5f6..."
}
```

**Response (Not Authenticated):**
```json
{
  "authenticated": false,
  "csrf_token": "a1b2c3d4e5f6..."
}
```

---

## Profile

### Get Profile

**GET** `/api/profile/get.php`

```bash
curl http://localhost:8000/api/profile/get.php \
  -H "Cookie: fitai_session=your_session_cookie"
```

**Response (200 OK):**
```json
{
  "success": true,
  "profile": {
    "id": 1,
    "email": "test@fitai.com",
    "goal": "muscle_gain",
    "level": "intermediate",
    "days_per_week": 4,
    "session_minutes": 60,
    "equipment": "gym",
    "constraints": null,
    "availability": {
      "monday": {"available": true, "time": "morning"},
      "tuesday": {"available": true, "time": "morning"},
      "wednesday": {"available": false},
      "thursday": {"available": true, "time": "evening"},
      "friday": {"available": true, "time": "morning"},
      "saturday": {"available": true, "time": "afternoon"},
      "sunday": {"available": false}
    },
    "created_at": "2025-01-08 12:00:00"
  }
}
```

---

### Update Profile

**POST** `/api/profile/update.php`

```bash
curl -X POST http://localhost:8000/api/profile/update.php \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: your_csrf_token" \
  -H "Cookie: fitai_session=your_session_cookie" \
  -d '{
    "goal": "fat_loss",
    "level": "beginner",
    "days_per_week": 3,
    "session_minutes": 45,
    "equipment": "home",
    "constraints": "lower back pain",
    "availability": {
      "monday": {"available": true, "time": "evening"},
      "wednesday": {"available": true, "time": "evening"},
      "friday": {"available": true, "time": "evening"}
    },
    "csrf_token": "your_csrf_token"
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "profile": {
    "goal": "fat_loss",
    "level": "beginner",
    "days_per_week": 3,
    "session_minutes": 45,
    "equipment": "home",
    "constraints": "lower back pain",
    "availability": {...}
  }
}
```

---

## Plans

### Get Current Week's Plan

**GET** `/api/plans/get.php`

```bash
curl "http://localhost:8000/api/plans/get.php" \
  -H "Cookie: fitai_session=your_session_cookie"
```

**Optional query parameter**: `?week_start=2025-01-06`

**Response (With Plan):**
```json
{
  "success": true,
  "has_plan": true,
  "plan": {
    "id": 1,
    "week_start": "2025-01-06",
    "days": [
      {
        "id": 1,
        "date": "2025-01-06",
        "title": "Upper A",
        "estimated_minutes": 54,
        "sessions": [
          {
            "id": 1,
            "exercise": "Barbell Bench Press",
            "sets": 4,
            "reps": "8-10",
            "rest_sec": 90,
            "notes": "Primary chest compound exercise"
          },
          {
            "id": 2,
            "exercise": "Lat Pulldown",
            "sets": 3,
            "reps": "10-12",
            "rest_sec": 90,
            "notes": "Vertical pulling exercise"
          }
        ],
        "log": null
      },
      {
        "id": 2,
        "date": "2025-01-07",
        "title": "Lower A",
        "estimated_minutes": 48,
        "sessions": [...],
        "log": {
          "status": "done",
          "fatigue_rating": 3,
          "notes": "Felt strong today",
          "logged_at": "2025-01-07 18:30:00"
        }
      }
    ],
    "principles": [
      "Progressive overload: aim to increase weight or reps each week",
      "Focus on controlled movements with proper form"
    ],
    "notes": [
      "Warm up for 5-10 minutes before each session",
      "Ensure adequate protein intake (1.6-2.2g per kg body weight)"
    ],
    "is_adjusted": false,
    "created_at": "2025-01-06 08:00:00"
  }
}
```

**Response (No Plan):**
```json
{
  "success": true,
  "has_plan": false,
  "week_start": "2025-01-06",
  "plan": null
}
```

---

### Generate New Plan

**POST** `/api/plans/generate.php`

```bash
curl -X POST http://localhost:8000/api/plans/generate.php \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: your_csrf_token" \
  -H "Cookie: fitai_session=your_session_cookie" \
  -d '{"csrf_token": "your_csrf_token"}'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Plan generated successfully",
  "plan": {
    "id": 1,
    "week_start": "2025-01-06",
    "days": [...],
    "principles": [...],
    "notes": [...],
    "is_adjusted": false
  }
}
```

---

### Regenerate Plan (Force)

**POST** `/api/plans/regenerate.php`

```bash
curl -X POST http://localhost:8000/api/plans/regenerate.php \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: your_csrf_token" \
  -H "Cookie: fitai_session=your_session_cookie" \
  -d '{"csrf_token": "your_csrf_token"}'
```

**Response (201 Created):**
Same as generate endpoint.

---

### Adjust Next Week's Plan

**POST** `/api/plans/adjust.php`

Based on current week's performance logs, generates an adjusted plan for next week.

```bash
curl -X POST http://localhost:8000/api/plans/adjust.php \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: your_csrf_token" \
  -H "Cookie: fitai_session=your_session_cookie" \
  -d '{"csrf_token": "your_csrf_token"}'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Adjusted plan generated for next week",
  "plan": {
    "id": 2,
    "week_start": "2025-01-13",
    "days": [...],
    "principles": [...],
    "notes": [
      "Great job completing 75% of last week's workouts!",
      "Your fatigue levels were moderate. Maintaining similar intensity."
    ],
    "is_adjusted": true
  },
  "adjustment_based_on": {
    "completion_rate": 75,
    "average_fatigue": 3.2
  }
}
```

---

## Logs

### Save Workout Log

**POST** `/api/logs/save.php`

```bash
curl -X POST http://localhost:8000/api/logs/save.php \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: your_csrf_token" \
  -H "Cookie: fitai_session=your_session_cookie" \
  -d '{
    "plan_day_id": 1,
    "status": "done",
    "fatigue_rating": 3,
    "notes": "Great workout, increased weight on bench press",
    "csrf_token": "your_csrf_token"
  }'
```

**Status options**: `done`, `skipped`, `partial`

**Fatigue rating**: 1-5 (optional)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Workout log saved",
  "log": {
    "id": 1,
    "plan_day_id": 1,
    "status": "done",
    "fatigue_rating": 3,
    "notes": "Great workout, increased weight on bench press"
  }
}
```

---

### Get Workout Logs

**GET** `/api/logs/get.php`

```bash
curl "http://localhost:8000/api/logs/get.php?limit=10" \
  -H "Cookie: fitai_session=your_session_cookie"
```

**Optional query parameters**:
- `week_start`: Filter by week (YYYY-MM-DD)
- `limit`: Number of logs to return (default 50, max 100)

**Response (200 OK):**
```json
{
  "success": true,
  "logs": [
    {
      "id": 1,
      "plan_day_id": 1,
      "date": "2025-01-06",
      "title": "Upper A",
      "week_start": "2025-01-06",
      "status": "done",
      "fatigue_rating": 3,
      "notes": "Great workout",
      "logged_at": "2025-01-06 18:30:00"
    }
  ],
  "stats": {
    "total_logged": 1,
    "completed": 1,
    "skipped": 0,
    "partial": 0,
    "average_fatigue": 3.0
  }
}
```

---

## Dashboard

### Get Dashboard Stats

**GET** `/api/dashboard/stats.php`

```bash
curl http://localhost:8000/api/dashboard/stats.php \
  -H "Cookie: fitai_session=your_session_cookie"
```

**Response (200 OK):**
```json
{
  "success": true,
  "stats": {
    "today_session": {
      "id": 3,
      "date": "2025-01-08",
      "title": "Upper B",
      "estimated_minutes": 54,
      "status": "pending",
      "exercises": [
        {"exercise_name": "Dumbbell Row", "sets": 4, "reps": "8-10", ...}
      ]
    },
    "next_session": {
      "id": 4,
      "date": "2025-01-09",
      "title": "Lower B",
      "estimated_minutes": 48
    },
    "has_plan": true,
    "week_start": "2025-01-06",
    "weekly_completion": 50,
    "completed_this_week": 2,
    "total_this_week": 4,
    "current_streak": 3,
    "total_completed": 15,
    "recent_activity": [
      {
        "date": "2025-01-07",
        "title": "Lower A",
        "status": "done",
        "fatigue_rating": 3,
        "logged_at": "2025-01-07 18:30:00"
      }
    ]
  }
}
```

---

## Exercises

### List Exercises

**GET** `/api/exercises/list.php`

```bash
curl "http://localhost:8000/api/exercises/list.php?equipment=gym&muscle_group=chest" \
  -H "Cookie: fitai_session=your_session_cookie"
```

**Optional query parameters**:
- `equipment`: none, home, gym
- `muscle_group`: chest, back, shoulders, biceps, triceps, legs, core, full_body, cardio
- `difficulty`: beginner, intermediate, advanced

**Response (200 OK):**
```json
{
  "success": true,
  "total": 5,
  "exercises": [
    {
      "id": 40,
      "name": "Barbell Bench Press",
      "muscle_group": "chest",
      "equipment": "gym",
      "difficulty": "intermediate",
      "description": "Primary chest compound exercise",
      "instructions": "Lie on bench, lower bar to chest, press up..."
    }
  ],
  "grouped": {
    "chest": [
      {"id": 40, "name": "Barbell Bench Press", ...}
    ]
  }
}
```

---

## AI Service Endpoints

These endpoints are called by the PHP backend, not directly by the frontend.

### Health Check

**GET** `http://localhost:8001/health`

```bash
curl http://localhost:8001/health
```

**Response:**
```json
{
  "status": "healthy",
  "service": "FitAI Plan Generator",
  "version": "1.0.0",
  "gemini_available": false,
  "timestamp": "2025-01-08T12:00:00.000000"
}
```

---

### Generate Plan (AI Service)

**POST** `http://localhost:8001/generate_plan`

```bash
curl -X POST http://localhost:8001/generate_plan \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "week_start": "2025-01-06",
    "profile": {
      "goal": "muscle_gain",
      "level": "intermediate",
      "days_per_week": 4,
      "session_minutes": 60,
      "equipment": "gym",
      "constraints": null,
      "availability": null
    },
    "exercises": [
      {"name": "Barbell Bench Press", "muscle_group": "chest", "equipment": "gym", "difficulty": "intermediate"},
      {"name": "Lat Pulldown", "muscle_group": "back", "equipment": "gym", "difficulty": "beginner"}
    ]
  }'
```

---

### Adjust Plan (AI Service)

**POST** `http://localhost:8001/adjust_plan`

```bash
curl -X POST http://localhost:8001/adjust_plan \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "week_start": "2025-01-13",
    "profile": {...},
    "exercises": [...],
    "previous_plan": {
      "week_start": "2025-01-06",
      "principles": [...],
      "days": [
        {"date": "2025-01-06", "title": "Upper A", "status": "done", "fatigue_rating": 3}
      ]
    },
    "logs_summary": {
      "completed_days": 3,
      "total_days": 4,
      "completion_rate": 75,
      "average_fatigue": 3.2
    }
  }'
```

---

## Error Responses

All endpoints return consistent error responses:

**400 Bad Request:**
```json
{
  "error": true,
  "message": "Missing required fields: email, password"
}
```

**401 Unauthorized:**
```json
{
  "error": true,
  "message": "Authentication required"
}
```

**403 Forbidden:**
```json
{
  "error": true,
  "message": "Invalid CSRF token"
}
```

**404 Not Found:**
```json
{
  "error": true,
  "message": "Endpoint not found"
}
```

**500 Internal Server Error:**
```json
{
  "error": true,
  "message": "Database connection failed"
}
```

**503 Service Unavailable:**
```json
{
  "error": true,
  "message": "AI service unavailable. Please try again later."
}
```
