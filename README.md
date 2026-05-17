# PFMS

PFMS is a personal finance management system for tracking income, expenses, budgets, semesters, reminders, and email reports in one place.

It is built as a split frontend and backend application:
- `backend/` powers the API, business logic, authentication, reporting, and scheduled alerts
- `frontend/` provides the Vue 3 user interface for dashboards, transactions, budgets, and settings

## What It Does

PFMS helps users:
- record income and expense transactions
- organize spending by categories and types
- create and monitor monthly budgets
- receive automatic budget alert emails when spending is close to or above budget
- receive scheduled financial report emails with insights and recommendations
- manage semester-based financial views and filters
- update profile and notification preferences in one place

## Key Features

- Dashboard overview with income, expense, net balance, and savings rate
- Transaction management with filters, search, and category tracking
- Budget setup and usage monitoring
- Email reminders and financial reports
- Automatic budget alerts with warning and urgent levels
- Semester setup with date-based matching and status labels
- Profile settings and reminder settings
- Test buttons for report email and alert email

## Tech Stack

### Frontend
- Vue 3
- Vite
- Vue Router
- Pinia
- Axios
- Tailwind CSS
- Flowbite

### Backend
- Laravel 8
- PHP 7.4+ / 8.x
- Laravel Sanctum
- Laravel Fortify
- SMTP email support

## Project Structure

```text
PFMS/
  backend/   Laravel API and scheduled jobs
  frontend/  Vue application
```

## Requirements

- PHP 7.4 or newer
- Composer
- Node.js 16+ recommended
- npm
- MySQL or another supported database
- SMTP credentials for email sending

## Local Setup

### 1. Backend Setup

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
```

Update your backend `.env` with your database and mail settings.

### 2. Frontend Setup

```bash
cd frontend
npm install
npm run dev
```

The frontend runs on `http://localhost:5173` by default.

### 3. Run the Backend

Use your preferred local server setup, or start Laravel manually:

```bash
cd backend
php artisan serve
php artisan schedule:work
```

If you use Laravel with a separate API URL, make sure the frontend points to the correct backend endpoint.

## Environment Variables

Common backend variables you may need:

- `APP_NAME`
- `APP_URL`
- `FRONTEND_URL=http://localhost:5173`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `MAIL_MAILER`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

## Email and Alerts

PFMS supports two kinds of email automation:

- budget alerts
- financial report emails

To let scheduled alerts run automatically, keep the Laravel scheduler running:

```bash
cd backend
php artisan schedule:work
```

For production, use a system cron or task scheduler to run Laravel's scheduler every minute.

## Main Modules

- Dashboard
- Transactions
- Budgets
- Financial Summary
- Financial Trend
- Semester Setup
- Reminders & Reports
- Profile Settings
- Transaction Setup

## Notes

- Semester-based filters are date-aware, so data is matched to the correct semester range.
- Budget alerts can be tested from the reminders page.
- Report emails and alert emails are linked back to the frontend at `http://localhost:5173` during local development.

## License

No license has been specified yet. Add one if you plan to share or publish this project.
