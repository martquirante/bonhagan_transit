-- =========================================================
-- BONHAGAN TRANSIT — Seed Data
-- =========================================================

-- 1. Bus Types
INSERT INTO public.bus_types (type_name, description)
VALUES
    ('Air-conditioned', 'Air-conditioned bus service with premium comfort and WiFi'),
    ('Ordinary', 'Standard regular fare ordinary bus service')
ON CONFLICT (type_name) DO NOTHING;

-- 2. Discount Types (Configurable baseline: 20%)
INSERT INTO public.discount_types (discount_name, discount_percentage, description)
VALUES
    ('Student', 20.00, 'Student discount applicable with valid school ID'),
    ('Senior Citizen', 20.00, 'Senior citizen discount applicable with valid OSCA ID')
ON CONFLICT (discount_name) DO NOTHING;

-- 3. Initial Accounts
-- Passwords:
-- admin@bonhagan.com     -> AdminPassword123!
-- conductor@bonhagan.com -> StaffPassword123!
-- driver@bonhagan.com    -> StaffPassword123!
-- passenger@bonhagan.com -> Passenger123!

INSERT INTO public.profiles (full_name, email, password_hash, mobile_number, role, account_status)
VALUES
    ('Bonhagan Admin', 'admin@bonhagan.com', '$2y$12$AfWNakeICdITASY67y9k5uHTR1j/yR.3MNZKJRbu7DT91tc73vnAO', '09170000001', 'admin', 'active'),
    ('Carl Conductor', 'conductor@bonhagan.com', '$2y$12$LKk7cTOWiAmCpou/O13/r.gbaNjmev5Kkukm21sNb/Nq0TZaMLEAW', '09170000002', 'staff', 'active'),
    ('Dan Driver', 'driver@bonhagan.com', '$2y$12$LKk7cTOWiAmCpou/O13/r.gbaNjmev5Kkukm21sNb/Nq0TZaMLEAW', '09170000003', 'staff', 'active'),
    ('Juan Dela Cruz', 'passenger@bonhagan.com', '$2y$12$lh6MEa/iShF9zeX3PI2cwez5iCtaLHxkZCR11.Gpa3AYsq.xI1wxy', '09170000004', 'passenger', 'active')
ON CONFLICT (email) DO NOTHING;

-- 4. Route Stops (Corridor: Monumento ↔ SJDM ↔ Sapang Palay / FVR)
INSERT INTO public.stops (stop_name, address, latitude, longitude)
VALUES
    ('Monumento Terminal', 'Monumento, Caloocan City', 14.6574, 120.9839),
    ('SM City Fairview', 'Quirino Highway, Novaliches, Quezon City', 14.7337, 121.0583),
    ('Tungkong Mangga', 'Quirino Highway, SJDM, Bulacan', 14.7892, 121.0776),
    ('Muzon Junction', 'Muzon, San Jose del Monte, Bulacan', 14.8143, 121.0182),
    ('Sapang Palay Proper', 'Sapang Palay, San Jose del Monte, Bulacan', 14.8525, 121.0558),
    ('FVR Terminal Area', 'Area B, FVR, Norzagaray / SJDM Boundary, Bulacan', 14.8710, 121.0694)
ON CONFLICT DO NOTHING;

-- 5. Routes (Northbound & Southbound)
INSERT INTO public.routes (route_code, route_name, direction, origin_name, destination_name, description, status)
VALUES
    ('MNL-BUL-NB', 'Monumento to Sapang Palay / FVR (Northbound)', 'northbound', 'Monumento Terminal', 'FVR Terminal Area', 'Primary northbound corridor connecting Metro Manila and Bulacan via Quirino Highway', 'active'),
    ('BUL-MNL-SB', 'Sapang Palay / FVR to Monumento (Southbound)', 'southbound', 'FVR Terminal Area', 'Monumento Terminal', 'Primary southbound corridor connecting Bulacan commuters to Metro Manila LRT/Monumento', 'active')
ON CONFLICT (route_code) DO NOTHING;

-- 6. Route Stops Association
DO $$
DECLARE
    nb_id BIGINT;
    sb_id BIGINT;
    s1 BIGINT; s2 BIGINT; s3 BIGINT; s4 BIGINT; s5 BIGINT; s6 BIGINT;
BEGIN
    SELECT id INTO nb_id FROM public.routes WHERE route_code = 'MNL-BUL-NB';
    SELECT id INTO sb_id FROM public.routes WHERE route_code = 'BUL-MNL-SB';

    SELECT id INTO s1 FROM public.stops WHERE stop_name = 'Monumento Terminal';
    SELECT id INTO s2 FROM public.stops WHERE stop_name = 'SM City Fairview';
    SELECT id INTO s3 FROM public.stops WHERE stop_name = 'Tungkong Mangga';
    SELECT id INTO s4 FROM public.stops WHERE stop_name = 'Muzon Junction';
    SELECT id INTO s5 FROM public.stops WHERE stop_name = 'Sapang Palay Proper';
    SELECT id INTO s6 FROM public.stops WHERE stop_name = 'FVR Terminal Area';

    -- Northbound sequence: 1 -> 2 -> 3 -> 4 -> 5 -> 6
    IF nb_id IS NOT NULL THEN
        INSERT INTO public.route_stops (route_id, stop_id, sequence_number, distance_from_origin, estimated_minutes_from_origin)
        VALUES
            (nb_id, s1, 1, 0.00, 0),
            (nb_id, s2, 2, 14.50, 40),
            (nb_id, s3, 3, 23.00, 65),
            (nb_id, s4, 4, 30.00, 85),
            (nb_id, s5, 5, 36.50, 105),
            (nb_id, s6, 6, 41.00, 120)
        ON CONFLICT (route_id, sequence_number) DO NOTHING;
    END IF;

    -- Southbound sequence: 6 -> 5 -> 4 -> 3 -> 2 -> 1
    IF sb_id IS NOT NULL THEN
        INSERT INTO public.route_stops (route_id, stop_id, sequence_number, distance_from_origin, estimated_minutes_from_origin)
        VALUES
            (sb_id, s6, 1, 0.00, 0),
            (sb_id, s5, 2, 4.50, 15),
            (sb_id, s4, 3, 11.00, 35),
            (sb_id, s3, 4, 18.00, 55),
            (sb_id, s2, 5, 26.50, 80),
            (sb_id, s1, 6, 41.00, 120)
        ON CONFLICT (route_id, sequence_number) DO NOTHING;
    END IF;
END;
$$;

-- 7. Buses and Seats
DO $$
DECLARE
    aircon_type_id BIGINT;
    ordinary_type_id BIGINT;
    b1_id BIGINT;
    b2_id BIGINT;
    r INT;
    c CHAR;
    seat_num VARCHAR(10);
BEGIN
    SELECT id INTO aircon_type_id FROM public.bus_types WHERE type_name = 'Air-conditioned';
    SELECT id INTO ordinary_type_id FROM public.bus_types WHERE type_name = 'Ordinary';

    -- Bus 101: Aircon
    INSERT INTO public.buses (bus_type_id, bus_number, plate_number, bus_name, seating_capacity, standing_capacity, status)
    VALUES (aircon_type_id, 'BT-101', 'NDB-1010', 'Bonhagan Star 101', 40, 10, 'active')
    ON CONFLICT (bus_number) DO UPDATE SET bus_name = EXCLUDED.bus_name
    RETURNING id INTO b1_id;

    -- Bus 102: Ordinary
    INSERT INTO public.buses (bus_type_id, bus_number, plate_number, bus_name, seating_capacity, standing_capacity, status)
    VALUES (ordinary_type_id, 'BT-102', 'NDB-2020', 'Bonhagan Express 102', 40, 15, 'active')
    ON CONFLICT (bus_number) DO UPDATE SET bus_name = EXCLUDED.bus_name
    RETURNING id INTO b2_id;

    -- Generate 40 seats (10 rows, columns A, B, C, D) for BT-101
    IF b1_id IS NOT NULL THEN
        FOR r IN 1..10 LOOP
            FOREACH c IN ARRAY ARRAY['A', 'B', 'C', 'D'] LOOP
                seat_num := r || c;
                INSERT INTO public.bus_seats (bus_id, seat_number, seat_row, seat_column, seat_type, is_active)
                VALUES (b1_id, seat_num, r, ASCII(c) - 64, 'regular', TRUE)
                ON CONFLICT (bus_id, seat_number) DO NOTHING;
            END LOOP;
        END LOOP;
    END IF;

    -- Generate 40 seats for BT-102
    IF b2_id IS NOT NULL THEN
        FOR r IN 1..10 LOOP
            FOREACH c IN ARRAY ARRAY['A', 'B', 'C', 'D'] LOOP
                seat_num := r || c;
                INSERT INTO public.bus_seats (bus_id, seat_number, seat_row, seat_column, seat_type, is_active)
                VALUES (b2_id, seat_num, r, ASCII(c) - 64, 'regular', TRUE)
                ON CONFLICT (bus_id, seat_number) DO NOTHING;
            END LOOP;
        END LOOP;
    END IF;
END;
$$;

-- 8. Fare Matrix and Fare Rules
DO $$
DECLARE
    nb_id BIGINT;
    sb_id BIGINT;
    aircon_type_id BIGINT;
    ordinary_type_id BIGINT;
    fm_nb_ac BIGINT;
    rs_rec RECORD;
    rs_dest RECORD;
    diff_dist NUMERIC;
    calc_fare NUMERIC;
BEGIN
    SELECT id INTO nb_id FROM public.routes WHERE route_code = 'MNL-BUL-NB';
    SELECT id INTO sb_id FROM public.routes WHERE route_code = 'BUL-MNL-SB';
    SELECT id INTO aircon_type_id FROM public.bus_types WHERE type_name = 'Air-conditioned';
    SELECT id INTO ordinary_type_id FROM public.bus_types WHERE type_name = 'Ordinary';

    -- Northbound Aircon Matrix
    INSERT INTO public.fare_matrices (route_id, bus_type_id, effective_from, base_rate, per_km_rate, is_active)
    VALUES (nb_id, aircon_type_id, CURRENT_DATE - INTERVAL '30 days', 15.00, 2.50, TRUE)
    RETURNING id INTO fm_nb_ac;

    -- Populate matrix fare rules for all ordered pairs
    FOR rs_rec IN SELECT * FROM public.route_stops WHERE route_id = nb_id ORDER BY sequence_number LOOP
        FOR rs_dest IN SELECT * FROM public.route_stops WHERE route_id = nb_id AND sequence_number > rs_rec.sequence_number ORDER BY sequence_number LOOP
            diff_dist := rs_dest.distance_from_origin - rs_rec.distance_from_origin;
            calc_fare := ROUND(15.00 + (diff_dist * 2.50), 2);

            INSERT INTO public.fare_rules (fare_matrix_id, origin_route_stop_id, destination_route_stop_id, fare_amount)
            VALUES (fm_nb_ac, rs_rec.id, rs_dest.id, calc_fare)
            ON CONFLICT (fare_matrix_id, origin_route_stop_id, destination_route_stop_id) DO NOTHING;
        END LOOP;
    END LOOP;
END;
$$;

-- 9. Scheduled Trips (Active trips for today and upcoming dates)
DO $$
DECLARE
    nb_id BIGINT;
    sb_id BIGINT;
    b1_id BIGINT;
    b2_id BIGINT;
    c_staff_id UUID;
    d_staff_id UUID;
    trip1_id BIGINT;
    trip2_id BIGINT;
BEGIN
    SELECT id INTO nb_id FROM public.routes WHERE route_code = 'MNL-BUL-NB';
    SELECT id INTO sb_id FROM public.routes WHERE route_code = 'BUL-MNL-SB';
    SELECT id INTO b1_id FROM public.buses WHERE bus_number = 'BT-101';
    SELECT id INTO b2_id FROM public.buses WHERE bus_number = 'BT-102';
    SELECT id INTO c_staff_id FROM public.profiles WHERE email = 'conductor@bonhagan.com';
    SELECT id INTO d_staff_id FROM public.profiles WHERE email = 'driver@bonhagan.com';

    -- Trip 1: Northbound today + 4 hours
    INSERT INTO public.trips (trip_code, route_id, bus_id, departure_datetime, estimated_arrival, status, standing_enabled, standing_capacity_override)
    VALUES (
        'TRIP-NB-' || TO_CHAR(NOW(), 'YYYYMMDD') || '-01',
        nb_id,
        b1_id,
        NOW() + INTERVAL '2 hours',
        NOW() + INTERVAL '4 hours',
        'scheduled',
        TRUE,
        10
    )
    ON CONFLICT (trip_code) DO NOTHING
    RETURNING id INTO trip1_id;

    -- Trip 2: Southbound tomorrow
    INSERT INTO public.trips (trip_code, route_id, bus_id, departure_datetime, estimated_arrival, status, standing_enabled, standing_capacity_override)
    VALUES (
        'TRIP-SB-' || TO_CHAR(NOW() + INTERVAL '1 day', 'YYYYMMDD') || '-01',
        sb_id,
        b2_id,
        NOW() + INTERVAL '1 day' + INTERVAL '8 hours',
        NOW() + INTERVAL '1 day' + INTERVAL '10 hours',
        'scheduled',
        FALSE,
        NULL
    )
    ON CONFLICT (trip_code) DO NOTHING
    RETURNING id INTO trip2_id;

    -- Assign staff to Trip 1
    IF trip1_id IS NOT NULL AND c_staff_id IS NOT NULL THEN
        INSERT INTO public.trip_staff (trip_id, staff_id, assignment_role)
        VALUES
            (trip1_id, c_staff_id, 'conductor'),
            (trip1_id, d_staff_id, 'driver')
        ON CONFLICT (trip_id, staff_id, assignment_role) DO NOTHING;
    END IF;
END;
$$;

