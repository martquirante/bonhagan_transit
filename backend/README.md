# BONHAGAN TRANSIT — BACKEND SYSTEM DOCUMENTATION

**Course:** IT305W – Advanced Web Applications  
**System:** Bus Scheduling, Reservation, and Online Ticketing System  
**Backend:** PHP 8+ (Pure PDO, Modular Service Architecture)  
**Database:** Neon PostgreSQL  
**Corridor:** Monumento ↔ SJDM ↔ Sapang Palay / FVR Area, Bulacan  

---

## 1. Project Overview

The **Bonhagan Transit** backend is a modular, secure, and production-grade PHP API built to service passenger booking, staff dispatch operations, and administrative fleet management.

### Key Architecture Highlights
- **100% PHP 8+ PDO Prepared Statements:** Immune to SQL injection attacks.
- **Neon Serverless PostgreSQL:** Connected via SSL with pooled connection support.
- **Session-Based Authentication:** Standard PHP session cookies with `HttpOnly`, `SameSite=Lax`, and automatic ID regeneration on login.
- **Role-Based Authorization:** Strict access controls for `passenger`, `staff`, and `admin`.
- **Dynamic Segment-Based Seat Allocation:** Seats are tracked by ordered stop intervals $(\text{seq}_{board} \to \text{seq}_{drop})$, allowing non-overlapping segments to share the same physical seat while preventing double-bookings.
- **Server-Side Fare Calculation:** Client prices are never trusted. All fares, matrices, and student/senior citizen discounts (configurable baseline 20%) are computed server-side.
- **Electronic Ticketing & Cryptographic QR Tokens:** Each passenger receives an individual e-ticket with a unique validation token for conductor scanning.

---

## 2. Directory Structure

```
backend/
├── public/
│   └── index.php                       # Health-check & API Index
├── config/
│   ├── database.php                    # Singleton PDO connection to Neon
│   ├── environment.php                 # .env parser, timezone & secure session
│   └── cors.php                        # CORS & preflight OPTIONS handler
├── api/
│   ├── auth/                           # Registration, login, logout, current user
│   ├── passengers/                     # Profiles and booking history
│   ├── trips/                          # Trip search, schedules, details
│   ├── bookings/                       # Multi-passenger booking & cancellation
│   ├── seats/                          # Segment seat map and availability
│   ├── discounts/                      # Student / Senior citizen document submission & review
│   ├── payments/                       # Online & terminal payment processing
│   ├── tickets/                        # Ticket issuance, QR generation, boarding validation
│   ├── admin/                          # Administrative fleet, fares, staff & report management
│   ├── staff/                          # Trip dispatch, passenger manifest, boarding logs, incidents
│   └── notifications/                  # User in-app notifications
├── middleware/
│   ├── auth-middleware.php             # Enforces authenticated session
│   ├── admin-middleware.php            # Requires 'admin' role
│   ├── staff-middleware.php            # Requires 'staff' or 'admin' role
│   └── csrf-middleware.php             # CSRF token generation and validation
├── services/
│   ├── BookingService.php              # Transactional multi-passenger reservations
│   ├── FareService.php                 # Route stop & matrix fare calculation
│   ├── SeatReservationService.php     # Segment-based seat & standing availability
│   ├── PaymentService.php              # Payment lifecycle & terminal workflows
│   ├── TicketService.php               # Electronic ticket & boarding verification
│   ├── QRCodeService.php               # Secure QR payload & SVG rendering
│   └── NotificationService.php         # In-app notifications
├── helpers/
│   ├── response.php                    # Standardized JSON response formatting
│   ├── validation.php                  # Input validator
│   ├── json-helper.php                 # JSON input parser
│   └── audit-logger.php                # Security and operational audit trail
├── storage/
│   ├── logs/                           # System and audit error logs
│   └── uploads/discounts/              # Private uploaded discount identity documents
├── .env.example
├── .htaccess
└── README.md
```

---

## 3. Seed Accounts for Testing

| Role | Email | Password |
|---|---|---|
| **Administrator** | `admin@bonhagan.com` | `AdminPassword123!` |
| **Conductor (Staff)** | `conductor@bonhagan.com` | `StaffPassword123!` |
| **Driver (Staff)** | `driver@bonhagan.com` | `StaffPassword123!` |
| **Passenger** | `passenger@bonhagan.com` | `Passenger123!` |

---

## 4. Standard JSON Response Format

### Success
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

### Error
```json
{
  "success": false,
  "message": "An error occurred.",
  "errors": {}
}
```

---

## 5. Running the Backend Locally

Using PHP's built-in development server from the project root:

```bash
php -S localhost:8000 -t backend/public
```

Access the health check endpoint:
```
http://localhost:8000/
```

