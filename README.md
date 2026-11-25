## ⚠️ Important Note: Live Preview Unavailable on GitHub
Because this project uses **PHP** and **MySQL** for its back-end logic and database operations, it cannot be hosted or previewed directly through GitHub Pages (which only supports static HTML, CSS, and JavaScript). 

This version is currently tested and run on a local server environment using **XAMPP** and **phpMyAdmin**.

---

## 🛠️ How to Run Locally (XAMPP)

1. **Install XAMPP:** Download and install [XAMPP](https://apachefriends.org) if you don't already have it.
2. **Move Project Files:** Copy this project folder into your XAMPP `htdocs` directory (usually located at `C:\xampp\htdocs\`).
3. **Start Local Servers:** Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
4. **Set Up the Database:**
   * Open your browser and go to `http://localhost/phpmyadmin`.
   * Create a new database named `library_system` *(or update this to your actual database name)*.
   * Import the `.sql` database file included in this repository *(if applicable)*.
5. **Launch the App:** Open your browser and visit `http://localhost/your-folder-name/` to test the system.
