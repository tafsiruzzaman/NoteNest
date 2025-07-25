# NoteNest

NoteNest is a secure, modern, and user-friendly personal cloud drive web application. It allows users to upload, preview, download, rename, organize, and manage TXT notes and image files—all with a polished, responsive interface and intuitive nested folders.

---

## Features

- **User Authentication:** Secure registration and login required.
- **Dashboard:** Modern, easy-to-use main page with navigation.
- **Notes Management:**
  - Upload, list, preview (modal), download, delete, and rename `.txt` note files
  - Organize notes in nested folders, just like images
- **Images Management:**
  - Upload, list, preview (modal), download, delete, and rename `.jpg`, `.jpeg`, `.png`, `.gif` images
  - Organize images in nested folders
- **Folder Management:** Unlimited nested folders for both notes and images. Rename & delete folders (if empty), navigate via breadcrumbs.
- **Responsive UI:** Built with [Bootstrap 5](https://getbootstrap.com/) and custom CSS for a modern, branded feel.
- **Security:** Strict user isolation—users can only access their own files and folders.
- **Favicon:** Custom favicon for a branded feel.

---

## Tech Stack

- **Backend:** PHP (7.4+ recommended)
- **Frontend:** HTML, Bootstrap 5, Font Awesome
- **Database:** MySQL
- **File Storage:** Files saved to local `/uploads/notes/` and `/uploads/images/`

---

## Screenshots

### 1. Register Page

![Register](screenshots/register.jpeg)

### 2. Login Page

![Login](screenshots/login.jpeg)

### 3. Dashboard

![Dashboard](screenshots/dashboard.jpeg)

### 4. My Notes Section

![My Notes](screenshots/my_notes.jpeg)

### 5. Add New Note

![My Notes](screenshots/new_note.jpg)

### 6. Note Preview Modal

![Note Preview](screenshots/note_preview.jpeg)

### 7. Download Note Dialog

![Download Note](screenshots/download_note.png)

### 8. My Images Section

![My Images](screenshots/my_images.jpeg)

### 9. Image Preview Modal

![Image Preview](screenshots/image_preview.jpeg)

---

## Installation & Setup

1. **Clone the Repository**
    ```bash
    git clone https://github.com/tafsiruzzaman/NoteNest.git
    cd NoteNest
    ```

2. **Create Database and Tables**
    - Create a new MySQL database, e.g., `note_nest`.
    - Use the following schema for full nested folder support:
      ```sql
      CREATE TABLE users (
          id INT PRIMARY KEY AUTO_INCREMENT,
          name VARCHAR(100) NOT NULL,
          email VARCHAR(255) NOT NULL UNIQUE,
          password VARCHAR(255) NOT NULL
      );
      CREATE TABLE note_folders (
          id INT PRIMARY KEY AUTO_INCREMENT,
          user_id INT NOT NULL,
          folder_name VARCHAR(100) NOT NULL,
          parent_id INT DEFAULT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id),
          FOREIGN KEY (parent_id) REFERENCES note_folders(id) ON DELETE CASCADE
      );
      CREATE TABLE notes (
          id INT PRIMARY KEY AUTO_INCREMENT,
          user_id INT NOT NULL,
          folder_id INT DEFAULT NULL,
          file_name VARCHAR(255) NOT NULL,
          stored_file VARCHAR(255) NOT NULL,
          uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id),
          FOREIGN KEY (folder_id) REFERENCES note_folders(id) ON DELETE SET NULL
      );
      CREATE TABLE image_folders (
          id INT PRIMARY KEY AUTO_INCREMENT,
          user_id INT NOT NULL,
          folder_name VARCHAR(100) NOT NULL,
          parent_id INT DEFAULT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id),
          FOREIGN KEY (parent_id) REFERENCES image_folders(id) ON DELETE CASCADE
      );
      CREATE TABLE images (
          id INT PRIMARY KEY AUTO_INCREMENT,
          user_id INT NOT NULL,
          folder_id INT DEFAULT NULL,
          file_name VARCHAR(255) NOT NULL,
          stored_file VARCHAR(255) NOT NULL,
          uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id),
          FOREIGN KEY (folder_id) REFERENCES image_folders(id) ON DELETE SET NULL
      );
      ```

3. **Configure the Application**
    - Update your database credentials in `config.php`.
    - Ensure `/uploads/notes/` and `/uploads/images/` directories exist and are writable by your web server.
    - Place the project folder in your web server's root (e.g., `htdocs/NoteNest/` for XAMPP).

4. **Access and Test**
    - Open your browser and navigate to `http://localhost/NoteNest`.

---

## Project Structure
```
/
├── config.php              
├── db.php                  
├── register.php            
├── login.php               
├── dashboard.php           
├── my_notes.php            
├── my_images.php           
├── note_download.php       
├── note_preview.php        
├── image_download.php      
├── image_preview.php       
├── uploads/
│   ├── notes/              
│   └── images/             
├── css/
│   └── dashboard.css       
├── screenshots/            
└── ...
```

## Contributors

- [@tafsiruzzaman](https://github.com/tafsiruzzaman)
- [@pritilatadea](https://github.com/pritilatadea)
- [@MonsurulHoqueAkib](https://github.com/MonsurulHoqueAkib)
- [@tjarin](https://github.com/tjarin)

---

## Security Highlights

- **User Data Isolation:** Each user can only see and manipulate their own files and folders.
- **Input Validation:** Strict filetype and size checks for all uploads.
- **Sanitization:** All file and form inputs are sanitized to prevent script injection and unsafe filenames.
- **Password Security:** Passwords are hashed before storage in the DB.
- **SQL Injection Protection:** All queries use prepared statements to prevent SQL injection.

---

## Potential Enhancements

- Multi-file and drag-and-drop uploads
- User profile and password change/reset
- File/folder sharing (private/public or with other users)
- Advanced search and filter for notes/images
- Tagging or metadata for files
- Dark/light mode toggle
- Activity log/history
- Mobile PWA support

---

## License

MIT License. © [@tafsiruzzaman](https://github.com/tafsiruzzaman), [@pritilatadea](https://github.com/pritilatadea), [@MonsurulHoqueAkib](https://github.com/MonsurulHoqueAkib), [@tjarin](https://github.com/tjarin)

---
