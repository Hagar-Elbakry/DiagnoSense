# DiagnoSense

## AI-Powered Patient Data Analysis and Clinical Decision Support

DiagnoSense is a smart clinical assistant for doctors. It transforms patient medical histories, laboratory results, radiology reports, and other medical documents into organized insights that are easier to review and act on.

The platform is designed to reduce diagnostic errors and save time by highlighting important information, comparing results over time, and providing decision-support insights. DiagnoSense supports the doctor throughout the patient journey: from onboarding and analysis to visits, medications, tasks, follow-up appointments, and patient communication.

> DiagnoSense supports clinical decision-making; it does not replace the doctor. The final medical decision always remains with the qualified healthcare professional.

## Why DiagnoSense?

Doctors often need to review large amounts of patient information spread across forms, medical histories, lab tests, radiology reports, and previous visits. Important details can be difficult to find, and trends may be missed when results are reviewed one document at a time.

DiagnoSense helps by:

- Organizing patient information into one medical file.
- Extracting and highlighting critical information from uploaded documents.
- Showing evidence for important extracted points.
- Comparing laboratory results over time.
- Suggesting possible diagnostic considerations based on the available patient data.
- Answering questions about a patient's file through DiagnoBot.
- Keeping medications, tasks, and upcoming visits synchronized with the patient mobile app.

The platform is especially useful for specialties that handle complex and high volumes of medical data, including internal medicine, cardiology, oncology, and neurology.

## Core Features

### Patient data and AI analysis

- Create and manage patient profiles.
- Store medical history, laboratory results, radiology reports, and other patient files.
- Run AI analysis when a patient is added or when files are re-analyzed.
- Generate a patient overview and AI summary.
- Extract key information and prioritize it as high, medium, or low priority.
- Display evidence associated with AI-generated key points.
- Provide decision-support insights with possible conditions, probability, status, and clinical reasoning.
- Compare historical laboratory results and show current values, changes, percentages, trends, and historical points.
- Ask DiagnoBot questions about a patient's file and receive answers grounded in the available reports.
- Add, update, and remove key points manually.

### Doctor workflow

- Register and authenticate doctors.
- Sign in with Google through Socialite.
- Log in and log out using Sanctum authentication.
- Verify email addresses or phone numbers using OTP.
- Reset passwords through OTP verification.
- Edit or delete a doctor profile.
- Manage patients, visits, medications, and clinical tasks.
- View dashboard statistics including:
  - Patient status distribution.
  - Top diseases.
  - Dashboard summary.
  - Today's visits.
- Receive web notifications for relevant events.
- Submit support tickets.

### Patient workflow

Doctors create patient accounts. Patients activate their accounts and set their passwords through the mobile application.

The patient mobile experience provides access to:

- Medical files and medical history.
- Laboratory and radiology information.
- Medications.
- Upcoming and historical visits.
- Assigned tasks.
- A timeline of patient activity.
- Mobile notifications.
- Task completion actions.

### Visits, medications, and tasks

A doctor can:

- Schedule the patient's next visit.
- Add medications.
- Create tasks such as laboratory tests and radiology requests.
- Update or remove medications and tasks.
- Notify the patient immediately through the mobile application.

### Billing and subscriptions

Doctors can use one of the following monthly plans or switch to pay-per-use billing. The seeded plans are:

| Plan | Price | Duration | Summaries | Features |
| --- | ---: | ---: | ---: | --- |
| Basic | EGP 1,200 | 30 days | 200 | Key Important Information, Comparative Analysis |
| Pro | EGP 3,000 | 30 days | 350 | Basic features, Decision Support |
| Premium | EGP 5,500 | 30 days | 550 | Pro features, DiagnoBot |

Pay-per-use charges EGP 50 per analyzed file according to the current application configuration.

The billing system supports:

- Wallet charging through Paymob.
- Subscription activation and cancellation.
- Pay-per-use activation.
- Transaction history.
- Subscription and credit notifications.
- AI-access validation based on the doctor's active plan, usage, or wallet balance.

## Architecture

DiagnoSense is a Laravel API backend consumed by a doctor web application and a patient mobile application.

```text
Doctor Web App                    Patient Mobile App
       |                                  |
       +------------ Laravel API --------+
                         |
       +-----------------+------------------+
       |                 |                  |
   MySQL database   Azure Blob Storage   Sanctum auth
       |                 |                  |
       +---------- AI analysis service ----+
                         |
       +---------+----------------+---------+
       |         |                |         |
    Reverb   Firebase          Paymob    Mail/SMS
   Web app   Patient push       Billing   Brevo/Vonage
 notifications notifications
```

### Data flow

1. A doctor creates a patient and submits structured information and medical files.
2. Patient files are stored in Azure Blob Storage.
3. The application prepares the patient's structured history and temporary file URLs for analysis.
4. An asynchronous Laravel queue job sends the data to the configured AI analysis service.
5. The analysis result is stored and used to create key points, decision-support records, and comparative data.
6. The doctor retrieves the results through the API and can ask DiagnoBot questions about the patient file.
7. Follow-up actions such as visits, medications, and tasks are sent to the patient through real-time and push notification channels.

For document-heavy deployments, the broader processing architecture can include OCR for scanned documents, data normalization, and vector search for fast retrieval from patient records.

## Technology Stack

- **Backend:** PHP 8.3+, Laravel 13
- **Authentication:** Laravel Sanctum
- **Database:** MySQL
- **Queue and cache:** Laravel database drivers by default
- **File storage:** Azure Blob Storage
- **AI integration:** Configurable external AI analysis service
- **Real-time web notifications:** Laravel Reverb and WebSockets
- **Mobile push notifications:** Firebase Cloud Messaging
- **Email:** Symfony Brevo Mailer
- **SMS and phone notifications:** Vonage Notification Channel
- **OTP:** `ichtrojan/laravel-otp`
- **Social authentication:** Laravel Socialite and Google OAuth
- **Payments:** Paymob
- **Testing:** Pest
- **Code style:** Laravel Pint
- **CI/CD:** GitHub Actions
- **Deployment target:** Railway

## Project Structure

```text
app/
  Actions/       Application actions for focused write operations
  Enums/         Domain enums
  Events/        Application events
  Exceptions/    Application-specific exceptions
  Helpers/       Shared helpers
  Http/
    Controllers/ API controllers
    Middleware/  Request middleware and access checks
    Requests/    Form request validation
    Resources/   API response resources
  Jobs/          Queued AI analysis and ingestion work
  Mail/          Email messages
  Models/        Eloquent models
  Notifications/ Email, SMS, web, and mobile notifications
  Policies/      Authorization policies
  Services/      Domain and integration services
  Rules/         Custom validation rules

database/
  factories/     Test data factories
  migrations/    Database schema
  seeders/       Plans and application seed data

routes/
  api.php        Versioned API routes under /api/v1
  channels.php   Broadcast channel authorization routes

tests/
  Feature/       End-to-end HTTP and application behavior tests
  Unit/          Unit tests
```

## Requirements

Choose one of the following setup options.

### Local PHP installation

- PHP 8.3 or newer
- Composer
- MySQL 8.0 or newer
- Required PHP extensions, including `pdo_mysql`, `mbstring`, `bcmath`, `fileinfo`, `openssl`, `curl`, and `zip`

### Docker / Laravel Sail

If you use Laravel Sail, you only need Docker installed and running. Docker provides PHP, MySQL, and the required PHP extensions inside the application container, so you do not need to install them manually on your host machine.

The included `compose.yaml` starts the Laravel application and MySQL services.

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/Hagar-Elbakry/DiagnoSense.git
cd DiagnoSense
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell, use:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Update `.env` with the local database credentials and the credentials for the integrations used by the environment.


### 4. Create the database and seed plans

```bash
php artisan migrate --seed
```

The database seeder creates the Basic, Pro, and Premium plans.


### 5. Start the application

```bash
php artisan serve
```

The API is then available at:

```text
http://localhost:8000/api/v1
```

### Laravel Sail

If Docker is available, start the containers with:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

On Windows, run Sail from WSL.


## API Overview

All application API routes are versioned under `/api/v1`.

| Area | Main routes |
| --- | --- |
| Authentication | `/auth/register`, `/auth/login/{type}`, `/auth/logout/{type}` |
| Google authentication | `/auth/google/redirect`, `/auth/google/callback`, `/auth/google/exchange` |
| OTP and passwords | `/auth/verify-contact`, `/auth/resend-otp`, `/auth/forget-password/{type}`, `/auth/verify-otp/{type}`, `/auth/reset-password/{type}` |
| Patients | `/patients`, `/patients/{patient}` |
| AI analysis | `/patients/{patient}/overview`, `/patients/{patient}/decision-support`, `/patients/{patient}/comparative-analysis` |
| DiagnoBot | `/patients/{patient}/chatbot/ask` |
| Key points | `/patients/{patient}/key-points` |
| Visits | `/patients/{patient}/visits`, `/next-visit` |
| Medications | `/visits/{visit}/medications`, `/medications` |
| Tasks | `/visits/{visit}/tasks`, `/tasks`, `/tasks/{task}/complete` |
| Dashboard | `/dashboard/status-distribution`, `/dashboard/top-diseases`, `/dashboard/summary`, `/dashboard/today-visits` |
| Subscriptions | `/subscriptions/plans`, `/subscriptions/{plan}/subscribe`, `/subscriptions/pay-per-use`, `/subscriptions/current` |
| Wallet | `/wallets/charge`, `/wallets/transactions` |
| Doctor profile | `/doctors/profile/edit`, `/doctors/profile`, `/change-password` |
| Patient mobile data | `/patient/medical-files`, `/timeline`, `/mobile-notifications` |
| Notifications | `/notifications`, `/notifications/unread-count`, `/notifications/{notification}/read` |
| Support | `/support` |

Most application routes require an authenticated Sanctum token. Authorization policies and middleware restrict access to the appropriate doctor or patient context.

## Running Tests

Run the complete test suite with:

```bash
php artisan test
```

The GitHub Actions workflows run the test suite and Pint checks on pushes and pull requests.