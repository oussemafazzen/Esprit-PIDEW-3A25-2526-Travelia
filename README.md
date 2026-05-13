<div align="center">

# ✈️ Travelia

### All-in-one Travel Management Platform

*Esprit School of Engineering — Projet Intégré de Développement Web (PIDEW) — 3A25 · 2025/2026*

[![PHP](https://img.shields.io/badge/PHP-≥8.1-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-6.4-000000?style=for-the-badge&logo=symfony&logoColor=white)](https://symfony.com/)
[![Python](https://img.shields.io/badge/Python-3.9+-3776AB?style=for-the-badge&logo=python&logoColor=white)](https://www.python.org/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-Proprietary-red?style=for-the-badge)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-blueviolet?style=for-the-badge)](https://phpstan.org/)

</div>

---

## 📖 About The Project

**Travelia** is a full-featured, web-based travel agency platform built with **Symfony 6.4**. It covers every stage of a traveller's journey in a single unified application — from searching and booking flights and accommodations, to managing payments, generating tickets, and getting AI-powered travel assistance.

This project was developed as part of the **Projet Intégré de Développement Web (PIDEW)** module at **Esprit School of Engineering** by a collaborative student team (class 3A25, academic year 2025/2026). Each team member owned one or more functional modules while sharing a common codebase.

---

## ✨ Features

### 🏨 Hébergement — Accommodation Management
- Full CRUD for hotels, guesthouses, resorts, and other accommodation types
- Image upload with automatic fallback to **Unsplash API** photos when no local image is provided
- Real-time **public holidays detection** per destination country (Abstract API + Nager.Date)
- Search and sort capabilities with live filtering
- **PDF export** of the full accommodation catalogue (wkhtmltopdf / KnpSnappy)
- Admin panel integration via EasyAdmin 5

### ✈️ Vols — Flight Search & Booking
- Live flight search powered by the **Amadeus / SerpAPI** integration
- Intelligent destination code resolver (supports city names, country names, IATA codes)
- **AI-powered flight recommendations** using a Python + scikit-learn model (budget / comfort / balanced profiles)
- Support for one-way and round-trip flights, multiple travel classes, and passenger count
- Promo code engine with server-side and client-side validation rules
- Graceful fallback to the internal billet database when the external API is unavailable

### 🎫 Billets — Ticket Management
- Admin CRUD with paginated list view, full-text search, and multi-column sorting
- Interactive **calendar view** of all departure dates
- Export to **Excel** (PhpSpreadsheet) and **PDF** (KnpSnappy)

### 📅 Réservations — Reservation Management
- End-to-end booking flow linked to authenticated clients
- Status tracking: pending → confirmed → cancelled
- Multiple payment methods: credit card, bank transfer (IBAN/RIB), cash
- Full client-side validation (no native browser tooltips) + server-side validation via Symfony Validator

### 🛖 Réservation Hébergement — Accommodation Booking
- Book accommodations with check-in / check-out dates
- Linked to the client account and reservation system

### 💳 Paiement — Payment
- Multi-method payment form (credit card, virement, espèces)
- Strict field-level validation (card number format, expiry date MM/YY, CVV, IBAN)
- Promo code discount calculation applied before final billing

### 🤖 Chatbot IA — AI Travel Assistant
- Conversational chatbot powered by the **Groq API** (LLaMA model)
- Maintains conversation history (up to 20 turns, XSS-sanitised)
- Context-aware answers about destinations, accommodations, and travel tips

### 🔐 Authentification & Sécurité
- Standard email + password login with hashed credentials
- **Google OAuth2** single sign-on (via KnpU OAuth2 Client Bundle)
- **Facial Recognition login** using `face-api.js` in the browser and Euclidean distance matching on the server (threshold: 0.6)
- Password reset by email (Symfony Mailer + Gmail SMTP)
- Role-based access control: `ROLE_USER` / `ROLE_ADMIN`

### 👤 Profil — User Profile
- View and update personal information
- Manage linked face recognition data

### 🛠️ Administration
- Full **EasyAdmin 5** back-office dashboard
- CRUD for all entities (clients, reservations, billets, hébergements, paiements)
- Restricted to `ROLE_ADMIN`

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| **Language** | PHP 8.1+ |
| **Framework** | Symfony 6.4 (LTS) |
| **ORM** | Doctrine ORM 3 + Doctrine Migrations |
| **Templating** | Twig 3, Symfony UX Turbo, Stimulus |
| **Database** | MySQL 8 (via XAMPP & WAMP) |
| **Admin Panel** | EasyAdmin Bundle 5 |
| **Authentication** | Symfony Security, KnpU OAuth2 (Google), face-api.js |
| **AI / ML** | Groq API (LLaMA), [Python 3](https://www.python.org/) + scikit-learn |
| **External APIs** | Amadeus / SerpAPI (flights), Unsplash (photos), Abstract API (holidays), Google OAuth2 |
| **PDF Export** | KnpSnappy + wkhtmltopdf |
| **Excel Export** | PhpSpreadsheet |
| **Pagination** | KnpPaginatorBundle |
| **Email** | Symfony Mailer + Gmail SMTP |
| **Testing** | PHPUnit 13, PHPStan 2 |
| **Dev Tools** | Symfony CLI, Symfony Web Profiler, Symfony Maker |
| **Dev Environment** | XAMPP (Apache + MySQL) / WAMP (Apache + MySQL) |

---

## ⚙️ Prerequisites

Make sure you have the following installed before setting up the project:

- **PHP** ≥ 8.1 (with extensions: `ctype`, `iconv`, `pdo_mysql`, `mbstring`, `xml`)
- **Composer** (latest stable)
- **XAMPP** (Apache + MySQL) — or any equivalent stack
- **Node.js** ≥ 18 + **npm**
- **Python** 3.9+ with `pip` (for the AI flight recommendation script)
- **wkhtmltopdf** — required for PDF exports ([download](https://wkhtmltopdf.org/downloads.html))
- **Symfony CLI** *(optional but recommended)* — for `symfony serve`

---

## 🚀 Installation & Setup

### 1. Clone the repository

```bash
git clone https://github.com/oussemafazzen/Esprit-PIDEW-3A25-2526-Travelia.git
cd Esprit-PIDEW-3A25-2526-Travelia
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install JavaScript / asset dependencies

```bash
php bin/console importmap:install
```

### 4. Configure your environment

Copy the example environment file and fill in your local values:

```bash
cp .env .env.local
```

Then edit `.env.local` — see the **[Environment Variables](#-environment-variables)** section below.

### 5. Create the database and run migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 6. Start the development server

**Option A — via XAMPP:**
1. Place the project folder inside `C:\xampp\htdocs\`
2. Start **Apache** and **MySQL** in the XAMPP Control Panel
3. Open `http://localhost/hebergementwebfinal/public/`

**Option B — via WAMP:**
1. Place the project folder inside `C:\wamp64\www\`
2. Start **WampServer** and ensure both Apache and MySQL services are green
3. Open `http://localhost/hebergementwebfinal/public/`

**Option C — via Symfony CLI:**
```bash
symfony server:start
```
Then open `http://127.0.0.1:8000`

### 7. (Optional) Install Python dependencies for AI recommendations

The AI flight recommendation engine uses a Python + scikit-learn model. Make sure [Python 3.9+](https://www.python.org/downloads/) is installed, then run:

```bash
cd ai/
pip install -r requirements.txt
```

---

## 🔑 Environment Variables

Create a `.env.local` file at the project root (this file is gitignored and must **never** be committed).
Configure the following variables:

```dotenv
# ── Application ──────────────────────────────────────────────────────────────
APP_ENV=dev
APP_SECRET=your_32_char_random_secret_here

# ── Database ──────────────────────────────────────────────────────────────────
DATABASE_URL="mysql://root:@localhost:3306/voyage"

# ── Mailer (Gmail SMTP) ───────────────────────────────────────────────────────
# Use an App Password if 2FA is enabled on the Gmail account
MAILER_DSN=smtp://your_email@gmail.com:your_app_password@smtp.gmail.com:587

# ── Google OAuth2 ─────────────────────────────────────────────────────────────
# Create credentials at: https://console.cloud.google.com/apis/credentials
GOOGLE_CLIENT_ID=your_google_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_google_client_secret

# ── Unsplash API ──────────────────────────────────────────────────────────────
# Register a free app at: https://unsplash.com/developers
UNSPLASH_ACCESS_KEY=your_unsplash_access_key

# ── Abstract API — Public Holidays ────────────────────────────────────────────
# Get a free key at: https://app.abstractapi.com/users/signup → Public Holidays API
ABSTRACT_HOLIDAYS_API_KEY=your_abstract_api_key
```

> **Note:** API keys for Amadeus / SerpAPI (flights) and Groq (chatbot) are configured inside the service classes or loaded from additional env variables. Refer to the respective service files under `src/Service/` for the exact variable names.

---

## 🧪 Running Tests

### Unit & Integration Tests (PHPUnit)

```bash
php bin/phpunit
```

### Static Analysis (PHPStan)

```bash
vendor/bin/phpstan analyse
```

---

## 📁 Project Structure

```
hebergementwebfinal/
├── ai/                         # Python ML scripts (flight recommendations)
├── assets/                     # JavaScript & CSS source files (Stimulus controllers)
├── config/                     # Symfony configuration (packages, routes, services)
├── migrations/                 # Doctrine database migrations
├── public/                     # Web root (index.php, uploads/)
│   └── uploads/
│       └── hotels/             # Uploaded accommodation images
├── src/
│   ├── Controller/             # All Symfony controllers
│   │   ├── FrontFlightController.php   # Flight search, booking, payment
│   │   ├── HebergementController.php   # Accommodation management
│   │   ├── BilletController.php        # Ticket management (admin)
│   │   ├── ReservationController.php   # Reservation management
│   │   ├── ChatbotController.php       # AI chatbot endpoint
│   │   ├── FaceAuthController.php      # Facial recognition login
│   │   ├── GoogleController.php        # Google OAuth2 callback
│   │   └── ...
│   ├── Entity/                 # Doctrine entities (DB models)
│   ├── Form/                   # Symfony Form types
│   ├── Repository/             # Doctrine repositories (custom queries)
│   ├── Security/               # Authenticators, voters
│   └── Service/                # Business logic & external API clients
│       ├── AmadeusFlightService.php    # Flight search API
│       ├── GroqChatService.php         # AI chatbot (Groq / LLaMA)
│       ├── HolidayService.php          # Public holidays API
│       ├── UnsplashService.php         # Auto hotel photo fetching
│       └── PromoCodeEvaluator.php      # Discount / promo code logic
├── templates/                  # Twig templates
│   ├── admin/                  # Admin panel views
│   ├── front/                  # Public-facing views (flights, payments)
│   ├── hebergement/            # Accommodation views
│   ├── billet/                 # Ticket views
│   └── ...
├── tests/                      # PHPUnit test suites
├── .env                        # Default environment variables (committed)
├── .env.local                  # Local overrides — NEVER commit this file
├── composer.json               # PHP dependency manifest
└── phpstan.neon                # PHPStan configuration
```

---

## 👥 Team

This project was built collaboratively by the **3A25** student team at **Esprit School of Engineering** as part of the PIDEW module.

| Name | Role / Module |
|---|---|
| **Oussema Fazzen** | Hébergement & Réservation Hébergement · Project Lead |
| **Syrine BenRjeb** | Vols & Billets |
| *(teammate)* | Réservations & Paiement |
| **Syrine Boukhit** | Authentification & Profil |
| **Tous** | Activités & Avis |



---

## 📬 Contact

For questions, collaboration requests, or bug reports:

- **Project Email:** admintravelia@gmail.com
- **GitHub Repository:** [oussemafazzen/Esprit-PIDEW-3A25-2526-Travelia](https://github.com/oussemafazzen/Esprit-PIDEW-3A25-2526-Travelia)
- **Institution:** [Esprit School of Engineering](https://esprit.tn), Tunis, Tunisia

---

## 📄 License

This project is **proprietary** software developed exclusively for academic purposes at Esprit School of Engineering. All rights reserved. Redistribution or commercial use without explicit written permission is prohibited.

---

<div align="center">
  Made with ❤️ by the Travelia Team · ESPRIT 3A25 · 2025/2026
</div>
