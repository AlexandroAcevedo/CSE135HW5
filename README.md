# CSE 135 Analytics Project

## Repository
[https://github.com/AlexandroAcevedo/CSE135HW5/new/main]

## Deployed Site
- Main site: https://test.cse135alex.site/index.html
- login: https://test.cse135alex.site/admin/login.php

## Project Overview
This project is a PHP/MySQL analytics platform deployed on a DigitalOcean Ubuntu droplet using Apache. The system collects analytics data from the frontend site, stores it in MySQL, and provides a protected admin dashboard for viewing reports, charts, comments, and exported summaries.

The goal of the project is to connect data collection, backend processing, user authentication, reporting, and presentational visualization into one complete analytics workflow.

## Architecture
### Hosting
- DigitalOcean Ubuntu Droplet

### Web Server
- Apache

### Backend
- PHP

### Database
- MySQL

### Frontend
- HTML, CSS, JavaScript

### Visualization
- Chart.js

### PDF Export
- DOMPDF

## Features Implemented
- Frontend analytics logging through `/log.php`
- MySQL-backed analytics storage
- Authentication system with session-based login
- Authorization rules for three user levels:
  - superadmin
  - analyst
  - viewer
- Admin dashboard
- Reports overview table
- Charts dashboard
- Three report categories:
  - Traffic Report
  - Behavior Report
  - Performance Report
- Analyst comments on reports
- PDF export for each report
- User management for superadmin

## Report Categories
### Traffic Report
Focuses on traffic concentration, top pages, daily traffic trend, and recent traffic activity.

### Behavior Report
Focuses on interaction patterns, event distribution, page/event combinations, and recent interaction samples.

### Performance Report
Focuses on page responsiveness using recorded load time samples, including average, minimum, and maximum load times.

## Authentication and Authorization
### Superadmin
Can access all sections, including user management, reports, charts, and exports.

### Analyst
Can access reports, charts, comments, and exports, but cannot manage users.

### Viewer
Can access read-only report views and exports, but cannot manage users or charts.

## Analytics Collection
Frontend pages send analytics data to `/log.php`, which inserts records into the `analytics` table.

Tracked fields include:
- cid
- page
- event
- user_agent
- referrer
- load_time_ms
- viewport_width
- viewport_height
- ip_address
- created

## AI Usage
AI was used during development to help:
- debug PHP and Apache issues
- structure role-based authorization
- improve report presentation and consistency
- draft documentation for report such as grammar correction.

The AI-generated suggestions were reviewed, tested, and adapted before being integrated into the project. I think AI had a good starter idea on what to do, but it made a lot of mistakes in formatting, architecture, and tool usage for analytics.

## What Worked Well
- Role-based access control integrated cleanly with the admin interface
- Report categories became more meaningful after separating traffic, behavior, and performance concerns
- PDF export successfully closes the loop between live analytics and shareable reports

## Limitations / Known Design Tradeoffs
- Chart.js charts appear in the live dashboard but are not embedded in exported PDFs because the PDF export is generated server-side with DOMPDF
- Viewer access is implemented as read-only report visibility rather than a more advanced saved-report publishing workflow
- Event tracking is still relatively lightweight and could be expanded further for richer behavioral analysis

## Future Improvements
- Add a true saved reports workflow for viewers
- Add report filters by date range
- Add richer event types for conversion analysis
- Improve performance tracking with more detailed timing metrics
- Embed chart images into exported PDFs
- Add email delivery for exported reports
- Add stronger 403/404 handling and more polished error pages
- Fix Export usage, and use a stronger method to load the chart images as a png to server and then export it on pdf with report.
