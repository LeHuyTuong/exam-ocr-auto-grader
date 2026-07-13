# Hệ thống chấm thi — Phase 1 (MVP)

## Stack
- **Backend + Admin:** Laravel 11 + Filament v3
- **Database:** MySQL/MariaDB
- **Mobile:** Flutter (Android APK)
- **AI OCR:** Google Gemini (vision + JSON mode)
- **Image storage:** Cloudinary
- **Auth:** Laravel Sanctum

## Milestones
| # | Milestone | Status |
|---|-----------|--------|
| M1 | Laravel foundation (migrations, models, auth, seeder) | ✅ |
| M2 | Filament admin (resources, export) | ✅ |
| M3 | AI OCR (GeminiVisionExtractor, Cloudinary, fuzzy-match) | ✅ |
| M4 | Mobile API endpoints (classes, exams, grades) | ✅ |
| M5 | Flutter app skeleton | ✅ |
| M6 | CI/CD deploy | ✅ |

## Accounts needed
- [ ] Gemini API key (Google AI Studio)
- [ ] Cloudinary credentials ✅
- [ ] cPanel hosting (FTP + MySQL)
- [ ] GitHub repo

## Deploy
1. Push to GitHub
2. Set secrets: `FTP_HOST`, `FTP_USER`, `FTP_PASS`, `FTP_TARGET_DIR`
3. Push `main` → auto-deploy backend via GH Actions
4. SSH into cPanel → `php artisan migrate --force`
5. Configure `.env` on host (DB, Gemini, Cloudinary)
6. Tag `v1.0.0` → CI builds APK artifact
