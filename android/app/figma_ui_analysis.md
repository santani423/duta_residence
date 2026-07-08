# Duta Residence App UI Analysis

Target Figma file: https://www.figma.com/design/Oc5FNqGRz0JhgLa0wEp9lC

## Source Summary

- Framework: Flutter with Material 3.
- Product: Duta Residence resident services app.
- Primary font: Inter via `GoogleFonts.interTextTheme`.
- Primary color seed: `#0F766E`.
- Light background: `#F3F8F6`.
- Light surface: `#FCFFFD`.
- Dark background: `#0B1120`.
- Dark surface: `#111827`.
- Card radius: `18`.
- Field/button radius: `12`.
- Base spacing scale: `4, 8, 12, 16, 24, 32`.
- Bottom navigation height: `74`.

## Core Screens

1. Login
   - Centered logo.
   - Login card max-width 440.
   - Title: "Masuk ke akun penghuni".
   - Subtitle: estate name.
   - Username and password text fields.
   - Filled login button.
   - Text button for forgot password.

2. Home Shell
   - App bar with logo, animated page title, notification icon.
   - Animated body transition.
   - Bottom navigation destinations: Beranda, Properti, Layanan, Notifikasi, Profil.

3. Dashboard / Beranda
   - Hero summary card with logo, notification tonal icon button, welcome text, unit/occupancy line.
   - Primary-container information banner.
   - Responsive stat grid: 2 columns on mobile, 4 on wide.
   - Quick actions card.
   - Customer and estate info cards.
   - Latest sections: bills, payments, service usage, documents, notifications.

4. Property / Properti
   - Header card with domain avatar, unit title, estate subtitle, status badge.
   - Detail Properti info rows.
   - Pengelolaan Tagihan explanatory card.

5. Services / Layanan
   - Horizontal segmented control tabs: Tagihan, Bayar, Komplain, Maintenance, Dokumen.
   - Bills list with invoice card and Bayar Tagihan button.
   - Payment list with Lanjut Bayar and Upload Bukti actions.
   - Manual proof bottom sheet form.
   - Complaint and maintenance ticket lists with floating create button.
   - Ticket form and detail bottom sheets.
   - Documents list.

6. Notifications / Notifikasi
   - Header row "Pusat Notifikasi" plus Tandai action.
   - Notification cards with title, status badge, message, date, read action.

7. Profile / Profil
   - Profile card with centered logo, avatar initial, name and email.
   - User info rows.
   - Theme segmented button: Terang, Gelap, Ikuti Sistem.
   - About list tiles.
   - Logout outlined button and confirmation dialog.

## Reusable Figma Components To Build

- AppLogo fallback.
- DutaCard.
- SectionHeader.
- InfoRow and InfoRows.
- StatusBadge variants: success, danger, warning, info, neutral.
- Filled button, tonal button, outlined button.
- Text field with leading icon.
- AppBar.
- BottomNavigationBar with selected/unselected states.
- Segmented services tab.
- StatCard.
- Invoice/payment/ticket/list cards.
- Async states: LoadingView, EmptyView, ErrorView.
- Bottom sheet templates for payment method, manual proof, ticket form, ticket detail.

## Proposed Figma Pages

- `00 Cover + Audit`
- `01 Foundations`
- `02 Components`
- `03 Mobile Screens`
- `04 States + Bottom Sheets`

## Figma Creation Status

The Figma file was successfully created in the `askara` team, but the Figma MCP write operation is currently blocked by the Starter plan tool-call limit:

> You've reached the Figma MCP tool call limit on the Starter plan.

Once the limit resets or the team plan is upgraded, populate the file using the analyzed scope above.
