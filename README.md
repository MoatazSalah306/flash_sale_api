

# Laravel Flash Sale / Stock Management API

This project implements a **Flash Sale / Product Stock Management API** using Laravel. It handles products, holds, orders, and webhook processing with idempotency support. The project is fully tested using PHPUnit.

## Table of Contents

* [Features](#features)
* [Installation](#installation)
* [Database Migrations](#database-migrations)
* [API Endpoints](#api-endpoints)
* [Testing](#testing)
* [API Documentation](#api-documentation)
* [Additional Features](#additional-features)
* [Notes](#notes)
* [Author](#author)

## Features

### Product Management

* CRUD for products (name, price, total stock)
* Tracks `reserved_stock` and `sold_stock` automatically
* Computes `available_stock` dynamically:

```php
available_stock = total_stock - reserved_stock - sold_stock
```

* Get product details including real-time available stock

### Hold Management

* Create a Hold for a product (`POST /holds`)
* Validates that requested quantity ≤ available stock
* Automatically sets `expires_at` timestamp
* Reserved stock is updated on hold creation
* Expired holds release reserved stock automatically

### Order Management

* Create an Order for an existing hold (`POST /orders`)
* Reserved stock is moved to sold stock upon order creation
* Handles status transitions: `Pending → Paid`
* Supports webhook-first scenario: Webhook can arrive before order exists
* Order will be updated to `Paid` when created

### Webhook & Idempotency

* Webhooks (`POST /api/payments/webhook`) are logged in `webhook_logs`
* Supports idempotency via `idempotency_key`
* Pending webhooks for non-existent orders are processed automatically when the order is created
* Prevents duplicate webhook logs

### Expiry & Background Processing

* Expired holds release reserved stock
* Works with `ExpireHolds` job (or manual expiry logic)
* To test jobs and automatic hold releases, run:

```bash
php artisan schedule:work
php artisan queue:work
```

## Additional Features

### Rate Limiting

* Implemented rate limiting on critical endpoints:

  * `POST /api/holds` - Prevents abuse of hold creation
  * `POST /api/orders` - Controls order creation frequency

### Timezone Handling

* Custom middleware to extract timezone from request headers
* Returns timezone-aware timestamps for all API responses
* Ensures correct datetime representation for users across different timezones

### API Response Standardization

* Custom `APIresponseService` for consistent API response formatting
* Hookable response structure with:

  * Standardized status codes
  * Uniform message formatting
  * Consistent data wrapping
  * Easy integration with frontend applications

## Installation

1. Clone the repository:

```bash
git clone https://github.com/MoatazSalah306/flash_sale_api.git
cd flash-sale-api
```

2. Install dependencies:

```bash
composer install
```

3. Copy `.env.example` to `.env` and set your database credentials:

```bash
cp .env.example .env
php artisan key:generate
```

4. Run migrations:

```bash
php artisan migrate
```

5. (Optional) Seed database with initial data if needed:

```bash
php artisan db:seed
```

## Database Migrations

### Products

```
id, name, price, total_stock, reserved_stock, sold_stock, timestamps
```

### Holds

```
id, product_id, qty, expires_at, status, timestamps
```

### Orders

```
id, hold_id, status, timestamps
```

### WebhookLogs

```
id, idempotency_key, order_id (nullable), status, processed_at, timestamps
```

## API Endpoints

| Method | Endpoint                | Description                               |
| ------ | ----------------------- | ----------------------------------------- |
| GET    | `/api/products/{id}`    | Get product details with available stock  |
| POST   | `/api/holds`            | Create a new hold for a product           |
| POST   | `/api/orders`           | Create a new order for a hold             |
| POST   | `/api/payments/webhook` | Receive webhook for payment/order updates |

### Example Responses:

**GET /api/products/{id}**

```json
{
  "status": true,
  "message": "Product fetched successfully",
  "data": {
    "id": 1,
    "name": "Flash Sale Product Example",
    "price": "105.00",
    "available_stock": 100
  }
}
```

**POST /api/holds**

```json
{
    "status": true,
    "message": "Hold created successfully",
    "data": {
        "holdID": 3,
        "expiresAt": "2025-12-03T09:52:52+02:00"
    }
}
```

**POST /api/orders**

```json
{
    "status": true,
    "message": "Order created successfully",
    "data": {
        "orderID": 3
    }
}
```

## Testing

All features are covered with PHPUnit tests. Includes tests for:

* Product retrieval with available stock calculation
* Hold creation and stock updates
* Order creation and stock transitions
* Expired hold stock release
* Webhook idempotency and pre-order arrival
* Rate limiting functionality
* Timezone middleware
* API response formatting

Run tests:

```bash
php artisan test
```

## API Documentation

An API documentation has been created to facilitate easier testing and integration. You can access it here: https://share.apidog.com/3585a767-0bbb-4f43-85d2-e7613a6fc992

## Notes

* Make sure to use the correct queue connection in `.env` for webhook/background jobs if testing async behavior:

```env
QUEUE_CONNECTION=database
```

* Holds and orders logic ensures no oversell occurs
* All relationships between products, holds, orders, and webhooks are properly maintained
* Rate limiting can be configured in `app/Http/Kernel.php`
* Timezone header can be set using `X-Timezone` header (e.g., `X-Timezone: Europe/Paris`)
* The `GET /api/products/{id}` endpoint provides real-time available stock calculation
* To test background jobs and automatic hold release, run:

```bash
php artisan schedule:work
php artisan queue:work
```

## Author

Developed and tested by Moataz Salah

This project was implemented as part of a Laravel coding task for Junior Software Engineer role.

