# Sapphire Stay

This file contains ready-to-use **Nano Banana prompts** for generating the **Use Case Diagram** and **ER Diagram** for the Sapphire Stay project.

---

## 1. Nano Banana Prompt for Use Case Diagram

Create a professional UML use case diagram for a hotel booking and management system named **Sapphire Stay Hotel Management System**. Use a clean white background, modern academic style, balanced layout, neat black and blue lines, readable labels, and presentation-quality formatting.

Actors to include only:
- Guest / Customer
- Staff
- Admin

Guest / Customer use cases:
- Register account
- Login
- Logout
- Browse rooms
- View room details
- View gallery
- Check room availability
- Book room
- Make payment
- View booking history
- View booking confirmation
- Cancel booking
- Send message to staff
- Receive message notifications
- Submit review
- Contact hotel

Staff use cases:
- Login
- Logout
- View dashboard
- View bookings
- Create walk-in booking
- View guests
- Manage check-in
- Manage check-out
- View payments
- View invoices
- Generate invoice
- Manage rooms
- Upload room images
- Message customers
- View unread messages

Admin use cases:
- All staff actions
- Manage staff and users
- Manage room categories
- Manage amenities
- View reports and revenue
- View dashboard analytics
- Manage direct messages
- Manage gallery/content
- Configure hotel settings

Important relationships:
- Book room includes Check room availability
- Book room includes Make payment
- Generate invoice extends successful booking/payment
- Send message to staff and Message customers belong to direct messaging
- View unread messages connects with message notifications
- Create walk-in booking is performed by staff/admin for front-desk guests

Style requirements:
- Draw a system boundary labeled Sapphire Stay Hotel Management System
- Use standard UML stick actors and oval use cases
- Keep Guest, Staff, and Admin clearly separated
- Show Admin as a specialized version of Staff if possible
- Do not include Stripe or any payment gateway actor
- Make it clean, academic, and high-resolution

---

## 2. Nano Banana Prompt for ER Diagram

Create a professional Entity Relationship Diagram (ERD) for a hotel booking and management system named **Sapphire Stay**. Use a clean database design layout with a white background, blue and dark gray color theme, clearly separated table boxes, PK/FK labels, crow's foot notation, and presentation-ready formatting.

Include these entities and fields:

### users
- id (PK)
- name
- email
- phone
- password_hash
- role
- avatar
- is_active
- email_verified_at
- remember_token
- created_at
- updated_at

### room_categories
- id (PK)
- name
- description
- created_at

### rooms
- id (PK)
- category_id (FK -> room_categories.id)
- room_number
- name
- description
- price_per_night
- capacity
- floor
- size_sqft
- bed_type
- status
- is_featured
- thumbnail
- created_at
- updated_at

### room_images
- id (PK)
- room_id (FK -> rooms.id)
- image_path
- caption
- sort_order

### amenities
- id (PK)
- name
- icon

### room_amenities
- room_id (PK, FK -> rooms.id)
- amenity_id (PK, FK -> amenities.id)

### bookings
- id (PK)
- booking_ref
- user_id (FK -> users.id)
- room_id (FK -> rooms.id)
- check_in
- check_out
- nights
- adults
- children
- subtotal
- tax_amount
- total_amount
- special_requests
- status
- checked_in_at
- checked_out_at
- handled_by (FK -> users.id)
- created_at
- updated_at

### payments
- id (PK)
- booking_id (FK -> bookings.id)
- user_id (FK -> users.id)
- amount
- currency
- method
- status
- stripe_payment_intent_id
- stripe_charge_id
- stripe_customer_id
- proof_image
- proof_submitted_at
- reviewed_by
- reviewed_at
- review_notes
- paid_at
- created_at
- updated_at

### invoices
- id (PK)
- booking_id (FK -> bookings.id)
- invoice_number
- issued_at
- due_date
- total
- notes
- generated_by (FK -> users.id, optional depending on schema version)
- pdf_path

### reviews
- id (PK)
- room_id (FK -> rooms.id)
- user_id (FK -> users.id)
- rating
- title
- body
- is_approved
- created_at

### gallery
- id (PK)
- title
- image_path
- category
- sort_order
- created_at

### contact_messages
- id (PK)
- name
- email
- phone
- subject
- message
- is_read
- created_at

### messages
- id (PK)
- sender_id (FK -> users.id)
- recipient_id (FK -> users.id)
- subject
- body
- is_read
- read_at
- created_at
- updated_at

### settings
- key (PK)
- value

Relationships to show:
- One room category has many rooms
- One room has many room images
- Many-to-many between rooms and amenities through room_amenities
- One user has many bookings
- One room has many bookings
- One booking has one invoice
- One booking has one or many payments
- One room has many reviews
- One user writes many reviews
- One user sends many messages
- One user receives many messages
- Staff/admin handles bookings through handled_by
- Staff/admin generates invoices through generated_by

Style requirements:
- Use crow's foot notation
- Mark PK and FK clearly
- Keep the database normalized and neatly aligned
- Make it look like a final year project or client presentation ERD
- High resolution and easy to read

---

## 3. Short Prompt Version

Create two separate professional diagrams for a hotel management system named Sapphire Stay: first, a UML use case diagram with Guest, Staff, and Admin actors only, including booking, messaging, invoices, room management, reviews, contact, and walk-in booking; second, a complete ER diagram with users, room_categories, rooms, room_images, amenities, room_amenities, bookings, payments, invoices, reviews, gallery, contact_messages, messages, and settings, using PK/FK labels and crow's foot relationships. Do not include Stripe as an actor in the use case diagram.




sudo /opt/lampp/lampp start

thunar /opt/lampp/htdocs