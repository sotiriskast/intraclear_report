# Merchant API Documentation

## Overview

This API provides merchant access to rolling reserve information, allowing merchants to view their reserve entries, get summaries, and manage their API authentication.

## Base URL

```
http://intraclear_jetstream.localhost/api/v1
```

## Authentication

The API uses token-based authentication with Laravel Sanctum.

### Obtaining an Access Token

**Endpoint:** `POST /auth/login`

**Rate Limit:** 10 requests per 60 minutes

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| account_id | integer | Yes | Your merchant account ID |
| api_key | string | Yes | Your merchant API key |

**Example Request:**
```json
{
  "account_id": 12345,
  "api_key": "your-api-key-here"
}
```

**Example Response:**
```json
{
  "success": true,
  "message": "Authentication successful",
  "data": {
    "merchant_id": 12345,
    "name": "Merchant Name",
    "token": "1|abcdefghijklmnopqrstuvwxyz1234567890"
  }
}
```

### Using the Token

Include the token in the Authorization header of all subsequent requests:

```
Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz1234567890
```

### Revoking a Token (Logout)

**Endpoint:** `POST /auth/logout`

**Headers Required:**
- Authorization: Bearer {token}

**Example Response:**
```json
{
  "success": true,
  "message": "Successfully logged out"
}
```

## Rolling Reserves Endpoints

All rolling reserve endpoints require authentication and the `merchant:read` ability.

### List Rolling Reserves

**Endpoint:** `GET /rolling-reserves`

**Rate Limit:** 60 requests per minute

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| per_page | integer | No | Number of results per page (default: 15, min: 5, max: 100) |
| status | string | No | Filter by status ("pending" or "released") |
| start_date | date | No | Filter by period start date (format: YYYY-MM-DD) |
| end_date | date | No | Filter by period end date (format: YYYY-MM-DD) |
| currency | string | No | Filter by original currency (3-character code) |

**Example Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "merchant_id": 456,
      "original_amount": 15479710,
      "original_currency": "JPY",
      "reserve_amount_eur": 955427,
      "exchange_rate": 0.0062,
      "period_start": "2024-11-21",
      "period_end": "2024-11-28",
      "release_due_date": "2025-05-28",
      "released_at": null,
      "status": "pending",
      "created_at": "2025-02-20T09:28:59.000000Z",
      "updated_at": "2025-02-20T09:28:59.000000Z"
    },
    // More records...
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

### Get Rolling Reserve Summary

**Endpoint:** `GET /rolling-reserves/summary`

**Rate Limit:** 30 requests per minute

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| currency | string | No | Filter by currency (3-character code) |

**Example Response:**
```json
{
  "success": true,
  "data": {
    "pending_reserves": {
      "USD": 12500.75,
      "EUR": 8750.50,
      "JPY": 154797.10
    },
    "pending_count": 24,
    "released_count": 18,
    "upcoming_releases": {
      "USD": 4200.25,
      "EUR": 3100.00
    }
  }
}
```

### Get Specific Rolling Reserve

**Endpoint:** `GET /rolling-reserves/{id}`

**Rate Limit:** 30 requests per minute

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Rolling reserve entry ID |

**Example Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "merchant_id": 456,
    "original_amount": 15479710,
    "original_currency": "JPY",
    "reserve_amount_eur": 955427,
    "exchange_rate": 0.0062,
    "period_start": "2024-11-21",
    "period_end": "2024-11-28",
    "release_due_date": "2025-05-28",
    "released_at": null,
    "status": "pending",
    "created_at": "2025-02-20T09:28:59.000000Z",
    "updated_at": "2025-02-20T09:28:59.000000Z"
  }
}
```

## Error Responses

### Validation Error (HTTP 422)
```json
{
  "success": false,
  "message": "Validation errors",
  "errors": {
    "account_id": ["The account id field is required."],
    "api_key": ["The api key field is required."]
  }
}
```

### Authentication Error (HTTP 401)
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

### Not Found Error (HTTP 404)
```json
{
  "success": false,
  "message": "Rolling reserve not found or does not belong to this merchant"
}
```

### Server Error (HTTP 500)
```json
{
  "success": false,
  "message": "Failed to retrieve rolling reserves",
  "error": "Error message details"
}
```

## Data Formats

### Amount Representation
All monetary amounts are represented in minor units (cents, pence, etc.) as integers:
- `original_amount`: The amount in the original currency's minor units
- `reserve_amount_eur`: The reserved amount converted to EUR in minor units (cents)

When displaying to users, divide by 100 for currencies with 2 decimal places.

### Exchange Rate
The `exchange_rate` field represents the conversion rate from the original currency to EUR.

### Dates and Times
All dates and timestamps follow ISO 8601 format:
- Dates: `YYYY-MM-DD`
- Timestamps: `YYYY-MM-DDTHH:MM:SS.uuuuuuZ`

## Rate Limiting

This API implements rate limiting to prevent abuse:
- Authentication: 10 requests per 60 minutes
- Reserve listing: 60 requests per minute
- Reserve summary and details: 30 requests per minute

If rate limits are exceeded, a 429 Too Many Requests response will be returned.

## Tips for Secure API Usage

1. Store your API key securely and never expose it in client-side code
2. Implement token refreshing if sessions need to last longer than 7 days
3. Always verify the success status in responses
4. Handle API errors gracefully in your applications

## Requesting Support

For technical support with API integration, please contact our support team at support@intraclear.com.
