# Booking System API

![CI](https://github.com/Bovisaloukou/Booking-System/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.4+-blue)
![Laravel](https://img.shields.io/badge/Laravel-12-red)
![License](https://img.shields.io/badge/License-MIT-green)

A complete RESTful API for a booking/reservation system built with **Laravel 12**. Features Stripe payment integration, multi-role access control, real-time notifications, and a full admin dashboard powered by Filament.

## Features

- **Multi-Role Access Control** — Admin, Provider, and Client roles via Spatie Permission
- **Service Catalog** — Categories and services with pricing and duration
- **Provider Management** — Provider profiles with specialties, services, and time slot availability
- **Time Slot System** — Configurable availability slots with automatic booking conflict prevention (pessimistic locking)
- **Booking Workflow** — Full lifecycle: pending → confirmed → completed (or cancelled)
- **Stripe Payments** — Payment Intent creation, webhook handling, and refund support via Laravel Cashier
- **Email Notifications** — Automated booking confirmation and status update emails
- **Real-Time Events** — Broadcasting via Laravel Echo for instant booking updates
- **Admin Dashboard** — Full CRUD management with Filament 3 (stats, filters, bulk actions)
- **API Documentation** — Auto-generated docs with Scribe + Postman collection
- **CI/CD** — Automated testing and code quality checks with GitHub Actions

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| Admin Panel | Filament 3 |
| Real-time | Livewire 3 + Laravel Echo |
| Payments | Stripe (via Laravel Cashier) |
| Auth | Laravel Sanctum (token-based) |
| Roles & Permissions | Spatie Laravel Permission |
| API Docs | Scribe |
| Testing | PHPUnit |
| CI/CD | GitHub Actions |
| Database | MySQL 8.0 |

## Architecture

```
app/
├── Enums/                # BookingStatus, PaymentStatus
├── Events/               # BookingCreated, BookingConfirmed (broadcastable)
├── Filament/
│   ├── Resources/        # Admin CRUD (Booking, Service, Provider, User, etc.)
│   └── Widgets/          # Dashboard stats & latest bookings
├── Http/
│   ├── Controllers/Api/  # REST API controllers
│   ├── Requests/         # Form request validation
│   └── Resources/        # API resource transformers
├── Models/               # Eloquent models with relationships
├── Notifications/        # Email + database notifications
├── Policies/             # Authorization (BookingPolicy)
└── Services/             # Business logic (BookingService, StripePaymentService)
```

## API Endpoints

### Public

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/register` | Register a new account |
| `POST` | `/api/login` | Login and get API token |
| `GET` | `/api/categories` | List all categories |
| `GET` | `/api/categories/{slug}` | Get category with services |
| `GET` | `/api/services` | List services (filterable) |
| `GET` | `/api/services/{slug}` | Get service details |
| `GET` | `/api/providers` | List active providers |
| `GET` | `/api/providers/{id}` | Get provider profile |
| `GET` | `/api/providers/{id}/slots` | Get available time slots |
| `GET` | `/api/providers/{id}/reviews` | Get provider reviews |

### Authenticated (Bearer Token)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/logout` | Revoke current token |
| `GET` | `/api/user` | Get authenticated user |
| `GET` | `/api/bookings` | List my bookings |
| `POST` | `/api/bookings` | Create a booking |
| `GET` | `/api/bookings/{id}` | Get booking details |
| `POST` | `/api/bookings/{id}/cancel` | Cancel a booking |
| `POST` | `/api/bookings/{id}/pay` | Create Stripe Payment Intent |
| `POST` | `/api/reviews` | Leave a review |

### Webhook

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/webhooks/stripe` | Stripe payment webhook |

## Installation

### Prerequisites

- PHP 8.4+
- Composer
- MySQL 8.0+

### Setup

```bash
# Clone the repository
git clone https://github.com/Bovisaloukou/Booking-System.git
cd Booking-System

# Install dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Configure your .env file
# DB_DATABASE=booking_system
# DB_USERNAME=your_user
# DB_PASSWORD=your_password
# STRIPE_KEY=your_stripe_key
# STRIPE_SECRET=your_stripe_secret

# Run migrations and seed
php artisan migrate --seed

# Generate API documentation
php artisan scribe:generate

# Start the server
php artisan serve
```

### Default Accounts (after seeding)

| Role | Email | Password |
|---|---|---|
| Admin | `admin@booking.test` | `password` |
| Provider | `provider1@booking.test` | `password` |
| Client | `client1@booking.test` | `password` |

### Admin Dashboard

Access the Filament admin panel at `/admin` using the admin credentials.

## Testing

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test suite
php artisan test --filter=BookingTest
```

## API Documentation

After running `php artisan scribe:generate`, the documentation is available at `/docs`.

## Database Schema

```
users ──┐
        ├── providers ──┬── provider_service ──── services ──── categories
        │               ├── time_slots
        │               ├── bookings ──┬── payments
        │               │              └── reviews
        └── bookings (as client)
```

## Booking Workflow

```
Client creates booking (status: pending)
    │
    ├── Payment via Stripe → Webhook confirms payment
    │                            │
    │                     Booking confirmed → Email sent + Event broadcast
    │
    ├── Provider/Admin confirms manually
    │
    ├── Cancel (by client or admin) → Slot freed, reason stored
    │
    └── Complete (by provider/admin) → Client can leave a review
```

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).
