
# NoteNest

NoteNest is a web-based personal cloud drive for managing notes and files. It allows users to upload, organize, share, and download files and folders securely. The application is built with PHP and MySQL, and features a modern, responsive interface.

---

## Features

- User registration and login
- Dashboard with quick access to personal, shared, favorite, and todo sections
- Upload, preview, download, rename, and delete files (any file type supported)
- Organize files in unlimited nested folders
- Share files and folders with other users (view-only)
- Manage shared access and revoke sharing
- Mark files and folders as favorites for quick access
- Todo list with reminders and notifications
- User profile management (name, phone, gender, photo, password)
- Notifications for sharing and todo reminders
- Security: user data isolation, input validation, password hashing, prepared statements

---

## Tech Stack

- PHP (8.x recommended)
- MySQL
- HTML, Bootstrap 5, Font Awesome

---

## Installation & Setup

1. **Clone the repository**
    ```bash
    git clone https://github.com/tafsiruzzaman/NoteNest.git
    cd NoteNest
    ```
2. **Create the database**
    - Import `database.sql` into your MySQL server.
3. **Configure the application**
    - Update your database credentials in `config.php` if needed.
    - Ensure the `uploads/notes/` and `img/user_photos/` directories exist and are writable.
    - Place the project folder in your web server root (e.g., `htdocs/NoteNest/` for XAMPP).
4. **Access the app**
    - Open your browser and go to `http://localhost/NoteNest`.

---

## Main Files & Structure

- `dashboard.php` — Main dashboard
- `my_note_nest.php` — Personal files and folders
- `shared_note_nest.php` — Files/folders shared with you
- `favorites.php` — Favorites management
- `todo.php` — Todo list and reminders
- `profile.php` — User profile
- `register.php`, `login.php`, `logout.php` — Authentication
- `share.php`, `share_management.php` — Sharing and access management
- `note_download.php`, `note_preview.php` — File download/preview
- `notifications.php` — Notification system
- `cron/todo_reminder.php` — Automated todo reminders
- `includes/` — Auth, DB, functions, navbar
- `uploads/notes/` — User files
- `img/user_photos/` — User profile photos

---

## Security Highlights

- Each user can only access their own files and folders
- All file and form inputs are validated and sanitized
- Passwords are hashed before storage
- All database queries use prepared statements

---

## Contributors

- [@tafsiruzzaman](https://github.com/tafsiruzzaman)
- [@pritilatadea](https://github.com/pritilatadea)
- [@MonsurulHoqueAkib](https://github.com/MonsurulHoqueAkib)
- [@tjarin](https://github.com/tjarin)

---

## Changes & Improvements in This Version

- Added file and folder sharing with other users (view-only)
- Added favorites system for quick access
- Added todo list with reminders and notification system
- Improved user profile management (photo, phone, gender, password)
- Enhanced security: stricter input validation, prepared statements everywhere
- Improved UI and navigation (Bootstrap 5, Font Awesome)
- Added notification dropdown and unread count
- Added cron job for todo reminders

---

## License

MIT License. © [@tafsiruzzaman](https://github.com/tafsiruzzaman), [@pritilatadea](https://github.com/pritilatadea), [@MonsurulHoqueAkib](https://github.com/MonsurulHoqueAkib), [@tjarin](https://github.com/tjarin)

