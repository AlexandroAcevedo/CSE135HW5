# GRADER.md

## Deployed Site
- Main site: https://test.cse135alex.site/index.html
- Admin login: https://test.cse135alex.site/admin/login.php

## Credentials

### Superadmin
- username: admin
- password: i<3P0we1167b4bygr0Nk

### Analyst
- username: Charizard
- password: Khant0B3$tRegi0N

### Viewer
- username: JohnViewer
- password: sUp3rs3cuRep@$s

## Suggested Grading Scenario

### Step 1: Visit the frontend site
Open:
- https://test.cse135alex.site/index.html

Navigate through the public site pages and click through the interface to generate analytics data.

### Step 2: Log in as superadmin
Open:
- https://test.cse135alex.site/admin/login.php

Use:
- admin login

Verify that the superadmin can access:
- Dashboard
- Reports Overview
- Traffic Report
- Behavior Report
- Performance Report
- Charts
- Manage Users

### Step 3: Review the reports
Open each of the three report categories and verify:
- chart visualization appears in the live dashboard
- data table appears
- analyst comments appear
- export button works

### Step 4: Test export
From each report page, click:
- Export PDF

Verify that a PDF downloads containing:
- report title
- generated timestamp
- summary table
- analyst comments

### Step 5: Log in as analyst
Use:
- Charizard credentials

Verify that the analyst can access:
- Dashboard
- Reports Overview
- Traffic Report
- Behavior Report
- Performance Report
- Charts

Verify that the analyst cannot access:
- Manage Users

### Step 6: Log in as viewer
Use:
- JohnViewer credentials

Verify that the viewer can access:
- Dashboard
- Reports Overview
- Traffic Report
- Behavior Report
- Performance Report
- PDF exports

Verify that the viewer cannot access:
- Charts
- Manage Users
- Analyst comment submission

## Known Issues / Areas of Concern
- Chart.js visualizations are shown in the live dashboard but are not currently embedded into exported PDFs because PDF generation is handled server-side using DOMPDF
- Viewer access is implemented as read-only report visibility rather than a more advanced saved-report publishing workflow
- Behavioral tracking is intentionally lightweight and focuses on pageview and click-style activity rather than a full production-level event taxonomy
- Performance reporting depends on newly collected load-time samples, so older analytics rows may not contain full performance metadata

## Notes for Grading
The project is intended to demonstrate:
- authentication
- authorization
- analytics data collection
- report generation
- presentational visualization
- PDF export

If some report sections appear to have limited data at first, please generate a small amount of frontend traffic by visiting and interacting with the public site before evaluating the dashboards. That way the data will be populated.
