# Laravel 12 Upgrade Guide

This document guides you through upgrading from Laravel 11 to Laravel 12.

## Composer Dependencies

Update the following in your `composer.json`:

```json
"laravel/framework": "^12.0",
"laravel/jetstream": "^5.4",
"laravel/sanctum": "^4.1",
"laravel/telescope": "^5.4",
```

## Update Steps

1. Run composer update:
```bash
composer update
```

2. Clear all caches:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

3. Update npm dependencies (if needed):
```bash
npm update
npm run build
```

## Laravel 12 Key Changes

- **PHP 8.2+**: Laravel 12 requires PHP 8.2 or higher (your app is using PHP 8.4)
- **New Service Provider Registration**: In Laravel 12, service providers are registered in the `bootstrap/providers.php` file (already done in your application)
- **Simplified Application Configuration**: Laravel 12 uses a streamlined approach to configuration in `bootstrap/app.php` (already implemented in your app)

## Potential Breaking Changes

### Route Registration

Check your routes for any changes in binding behavior. Laravel 12 modifies how route model binding works.

### Validation Rules

Some validation rules might have changed behavior. Review your form requests and validation logic.

### Cache and Session Drivers

If you're using custom cache or session drivers, they may need updates.

### Testing

Run your test suite after upgrading to identify any compatibility issues:

```bash
php artisan test
```

## Final Steps

1. Check for any deprecation warnings in logs after running the application
2. Review application functionality after the upgrade
3. Monitor performance to identify any regressions

## Troubleshooting

If you encounter issues:

1. Review Laravel 12 documentation for breaking changes
2. Check for any third-party package compatibility issues
3. Review application logs for errors