# Introduction

RESTful API for a booking/reservation system with Stripe payments, multi-role access control, and real-time notifications.

<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>

    This documentation provides all the information you need to integrate with the Booking System API.

    ## Authentication
    This API uses **Laravel Sanctum** token-based authentication. After registering or logging in, include the token in subsequent requests:
    `Authorization: Bearer {YOUR_TOKEN}`

    ## Roles
    - **client** — Can book services, make payments, and leave reviews
    - **provider** — Manages time slots and receives bookings
    - **admin** — Full access via the admin dashboard

    <aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
    You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>

