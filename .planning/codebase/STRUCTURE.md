# BookLoop — File Structure

```
my-laravel-app/
│
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Controller.php           # Base controller
│   │       ├── AuthController.php       # Register, login, logout, /me
│   │       ├── BookController.php       # Book CRUD + public browse
│   │       ├── MessageController.php    # Conversations + send message
│   │       ├── PasswordResetController.php  # Forgot/reset password
│   │       ├── ProfileController.php    # Profile view/update, history, notifications
│   │       ├── SwapRequestController.php    # Swap lifecycle (create/accept/decline/cancel)
│   │       └── UserController.php       # Public user profile
│   ├── Models/
│   │   ├── Book.php                     # Book model (belongsTo User)
│   │   ├── Message.php                  # Message model (sender/recipient)
│   │   ├── SwapRequest.php              # Swap model (requester, offeredBook, wantedBook)
│   │   └── User.php                     # User model (hasMany Books, Sanctum auth)
│   ├── Notifications/
│   │   ├── NewMessage.php               # Notification: new unread message
│   │   ├── SwapAccepted.php             # Notification: swap was accepted
│   │   └── SwapDeclined.php             # Notification: swap was declined
│   └── Policies/
│       └── BookPolicy.php               # Authorizes book operations to book owner
│
├── database/
│   └── migrations/
│       ├── ..._create_users_table.php
│       ├── ..._create_cache_table.php
│       ├── ..._create_jobs_table.php
│       ├── ..._create_personal_access_tokens_table.php
│       ├── ..._create_books_table.php
│       ├── ..._create_swap_requests_table.php
│       ├── ..._create_notifications_table.php
│       ├── ..._add_dismissed_flags_to_swap_requests_table.php
│       └── ..._create_messages_table.php
│
├── resources/
│   ├── css/
│   │   └── app.css                      # Global styles (Bootstrap + custom)
│   ├── js/
│   │   ├── app.js                       # Vue app entry point
│   │   ├── authStore.js                 # Auth state (user + token, localStorage)
│   │   ├── themeStore.js                # Dark/light mode state (localStorage)
│   │   ├── langStore.js                 # Language state EN/LV (localStorage)
│   │   ├── langMixin.js                 # Vue mixin: t() and setLocale() for components
│   │   ├── translations.js              # EN + LV translation string map
│   │   ├── coverColor.js                # Book cover color generator (id-based gradient)
│   │   ├── homeLogic.js                 # Extracted logic for Home browse/search
│   │   ├── bootstrap.js                 # Axios config bootstrap
│   │   ├── router/
│   │   │   └── router.js                # Vue Router routes + auth navigation guards
│   │   └── components/
│   │       ├── App.vue                  # Root component (<router-view>)
│   │       ├── AppNavbar.vue            # Shared navbar (logo, theme toggle, language)
│   │       ├── Home.vue                 # Public landing + book browse
│   │       ├── About.vue                # Static about page
│   │       ├── Login.vue                # Login form
│   │       ├── Register.vue             # Registration form
│   │       ├── ForgotPassword.vue       # Password reset request
│   │       ├── ResetPassword.vue        # Password reset (with token)
│   │       ├── Dashboard.vue            # Auth home: books, swaps, notifications
│   │       ├── Profile.vue              # Profile edit + swap history
│   │       ├── Messages.vue             # Messaging: conversation list + thread
│   │       ├── UserProfile.vue          # Public view of another user's library
│   │       ├── SwapModal.vue            # Modal: choose book to offer in swap
│   │       └── NotFound.vue             # 404 page
│   └── views/
│       └── welcome.blade.php            # Single Blade view — mounts Vue app on #app
│
├── routes/
│   ├── api.php                          # All REST API routes (Sanctum-protected + public)
│   ├── web.php                          # Catch-all route → welcome.blade.php
│   └── console.php                      # Artisan console routes
│
├── vite.config.js                       # Vite build config (laravel-vite-plugin + vue)
├── package.json                         # JS dependencies (Vue 3, Vue Router, Bootstrap, Axios)
├── composer.json                        # PHP dependencies
├── CLAUDE.md                            # Claude Code instructions for this project
├── darbaprocess.txt                      # Project work log (Latvian)
└── bookloop                             # Leftover SQLite database artifact (project root)
```
