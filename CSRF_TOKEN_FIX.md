# CSRF Token Fix for Admin Forms

## Issue
When trying to create or edit clusters/divisions, the error "Request blocked due to security policy" appeared. This was caused by missing CSRF (Cross-Site Request Forgery) tokens in the forms.

## Solution
Added CSRF token protection to all admin forms and their corresponding controllers.

## Changes Made

### 1. Cluster Form Template (`templates/admin/cluster/form.html.twig`)
Added CSRF token hidden field:
```twig
<input type="hidden" name="_token" value="{{ csrf_token('cluster_form') }}">
```

### 2. Cluster Controller (`src/Controller/Admin/OfficeClusterController.php`)
Added CSRF token validation in both `new()` and `edit()` methods:
```php
// Validate CSRF token
$token = $request->request->get('_token');
if (!$this->isCsrfTokenValid('cluster_form', $token)) {
    $this->addFlash('error', 'Invalid CSRF token.');
    return $this->redirectToRoute('admin_cluster_new');
}
```

### 3. Division Form Template (`templates/admin/division/form.html.twig`)
Added CSRF token hidden field:
```twig
<input type="hidden" name="_token" value="{{ csrf_token('division_form') }}">
```

### 4. Division Controller (`src/Controller/Admin/DivisionController.php`)
Added CSRF token validation in both `new()` and `edit()` methods:
```php
// Validate CSRF token
$token = $request->request->get('_token');
if (!$this->isCsrfTokenValid('division_form', $token)) {
    $this->addFlash('error', 'Invalid CSRF token.');
    return $this->redirectToRoute('admin_division_new');
}
```

## What is CSRF Protection?

CSRF (Cross-Site Request Forgery) protection prevents malicious websites from submitting forms on behalf of authenticated users. Symfony automatically validates CSRF tokens to ensure that form submissions come from legitimate sources.

## How It Works

1. When the form is rendered, Symfony generates a unique CSRF token
2. The token is included as a hidden field in the form
3. When the form is submitted, the controller validates the token
4. If the token is invalid or missing, the request is rejected

## Testing

After this fix, you should be able to:
1. Navigate to `/admin/clusters/new`
2. Fill in the cluster form
3. Submit successfully without the "Request blocked" error
4. Same for divisions at `/admin/divisions/new`

## Security Benefits

- Prevents unauthorized form submissions
- Protects against CSRF attacks
- Ensures forms are submitted from your application only
- Validates that the user intended to submit the form

## Note

All admin forms now have proper CSRF protection. This is a Symfony best practice and should always be implemented for forms that modify data.
