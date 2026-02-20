# NACOS Digital Library

The **NACOS Digital Library** is a secure, centralized academic resource platform developed for students of **Yaba College of Technology (YabaTech)** under the **National Association of Computer Students (NACOS)**.

The platform is designed to improve academic accessibility, preserve intellectual resources, and encourage continuous student engagement through controlled, non-downloadable digital content.

---

## 🎯 Purpose & Vision

- Provide a single source of truth for academic materials
- Eliminate uncontrolled file circulation
- Encourage repeat usage through bookmarks and UI engagement
- Maintain academic integrity via departmental approvals
- Empower students to contribute while enforcing quality control

---

## 🧠 Core Philosophy

- **Security before convenience**
- **Foundation before complexity**
- **Contribution with moderation**
- **Access without ownership**

---

## 🔑 Authentication & Access Control

- Matric Number–based authentication
- Secure registration and login
- Session-protected routes
- Role-aware access (Students, Governors, Course Reps)

---

## 🏠 Core Pages

### Home
- Latest academic releases
- Animated announcements
- Interactive “Did You Know?” trivia section

### Library
- Card-based book display
- Intelligent fuzzy search
- Secure, non-downloadable content viewing
- Floating “Add Book” action button

### Bookmarks
- Personalized saved books
- AJAX-powered add/remove
- Traffic retention through easy re-access

---

## 📄 Secure Document Handling

- Stream-only PDF viewer
- No downloads, no direct links
- Tokenized access control
- Server-level folder protection

---

## ⬆️ Book Upload & Processing

- Upload up to 450 images per book
- Automatic image compression
- OCR-based text enhancement
- Timestamp-based sorting
- Automatic PDF compilation

---

## 🛂 Approval Workflow

- Departmental review queue
- Governor and Course Rep moderation
- Manual approval before publication
- Academic integrity enforcement

---

## 🎨 UI & Experience

- NACOS Green & Yellow color system
- Smooth animations
- Responsive layouts
- Centralized headers, footers, and modals

---

## 🛠️ Technology Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MySQL
- **Server:** Apache
- **Version Control:** Git & GitHub

---

## 📁 Project Structure

nacos-digital-library/
│
├── .htaccess
├── index.php
│
├── includes/
│   ├── db.php
│   ├── session.php
│   ├── helpers.php
│   ├── auth_guard.php
│   ├── header.php
│   ├── footer.php
│   └── modal.php
│
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   ├── animations.css
│   │   ├── auth.css
│   │   └── library.css
│   │
│   ├── js/
│   │   ├── main.js
│   │   ├── modal.js
│   │   ├── fuzzy_search.js
│   │   ├── bookmarks.js
│   │   └── upload.js
│   │
│   └── images/
│
├── auth/
│   ├── login.php
│   ├── signup.php
│   └── logout.php
│
├── home/
│   └── home.php
│
├── library/
│   └── library.php
│
├── bookmarks/
│   └── bookmarks.php
│
├── view/
│   └── reader.php
│
├── upload/
│   ├── upload.php
│   ├── process_images.php
│   ├── ocr_process.php
│   ├── compile_pdf.php
│   └── upload_success.php
│
├── approval/
│   ├── queue.php
│   ├── review.php
│   └── approve.php
│
├── uploads/
│   └── protected_books/
│
├── .env.example
├── .gitignore
└── README.md


---

## 🚀 Future Enhancements

- Advanced analytics dashboard
- Mobile-first optimization
- Progressive Web App (PWA)
- API endpoints for institutional integration
- Activity logging and audit trails

---

## 📜 License

This project is licensed under the **MIT License**.

---

## 🏫 Ownership & Maintenance

Developed for **NACOS – Yaba College of Technology**  
Maintained by student developers for academic advancement.