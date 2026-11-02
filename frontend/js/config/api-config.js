/**
 * BONHAGAN TRANSIT — API Configuration
 */

const API_CONFIG = {
    // If backend is running on port 8000 (typical php -S localhost:8000 -t backend/public)
    // or relative if served from same origin
    BASE_URL: (window.location.port === '8000' || window.location.pathname.startsWith('/backend/public'))
        ? '/api'
        : 'http://localhost:8000/api',

    ENDPOINTS: {
        AUTH: {
            REGISTER: '/auth/register.php',
            LOGIN: '/auth/login.php',
            LOGOUT: '/auth/logout.php',
            CURRENT_USER: '/auth/current-user.php'
        },
        PASSENGERS: {
            GET_PROFILE: '/passengers/get-profile.php',
            UPDATE_PROFILE: '/passengers/update-profile.php',
            GET_BOOKINGS: '/passengers/get-bookings.php'
        },
        TRIPS: {
            SEARCH: '/trips/search-trips.php',
            SCHEDULES: '/trips/get-schedules.php',
            DETAILS: '/trips/get-trip-details.php'
        },
        BOOKINGS: {
            CREATE: '/bookings/create-booking.php',
            GET: '/bookings/get-booking.php',
            CANCEL: '/bookings/cancel-booking.php',
            CHECK_AVAILABILITY: '/bookings/check-availability.php'
        },
        SEATS: {
            GET_SEATS: '/seats/get-seats.php',
            RESERVE: '/seats/reserve-seat.php'
        },
        DISCOUNTS: {
            SUBMIT: '/discounts/submit-discount.php',
            VERIFY: '/discounts/verify-discount.php'
        },
        PAYMENTS: {
            CREATE: '/payments/create-payment.php',
            VERIFY: '/payments/verify-payment.php',
            STATUS: '/payments/payment-status.php'
        },
        TICKETS: {
            GENERATE: '/tickets/generate-ticket.php',
            GET_ALL: '/tickets/get-tickets.php',
            VALIDATE: '/tickets/validate-ticket.php'
        },
        NOTIFICATIONS: {
            GET_ALL: '/notifications/get-notifications.php',
            MARK_READ: '/notifications/mark-as-read.php'
        },
        STAFF: {
            ASSIGNED_TRIPS: '/staff/assigned-trips.php',
            PASSENGER_MANIFEST: '/staff/passenger-manifest.php',
            RECORD_BOARDING: '/staff/record-boarding.php',
            REPORT_INCIDENT: '/staff/report-incident.php'
        },
        ADMIN: {
            STATS: '/admin/dashboard-stats.php',
            BUSES: '/admin/manage-buses.php',
            ROUTES: '/admin/manage-routes.php',
            STOPS: '/admin/manage-stops.php',
            TRIPS: '/admin/manage-trips.php',
            BOOKINGS: '/admin/manage-bookings.php',
            FARES: '/admin/manage-fares.php',
            DISCOUNTS: '/admin/manage-discounts.php',
            PAYMENTS: '/admin/manage-payments.php',
            STAFF: '/admin/manage-staff.php',
            REPORTS: '/admin/generate-reports.php'
        }
    }
};

window.API_CONFIG = API_CONFIG;

