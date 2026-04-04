# Admin Panel UI/UX Standardization - Template Guide

## Status: 1 of 7 Files Updated
- ✅ **meeting-rooms.php** - COMPLETED (Blue sidebar + modern header)
- ⏳ **room-booking.php** - Pending
- ⏳ **announcements.php** - Pending  
- ⏳ **documents.php** - Pending
- ⏳ **userdocuments.php** - Pending
- ⏳ **my-bookings.php** - Pending
- ✅ **dashboard.php** - Already good
- ✅ **settings.php** - Already good

---

## Template Structure for All Files

### HTML Structure
```php
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Title - Romar</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/admin-theme.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --card-radius: 1.25rem;
            --card-shadow: 0 25px 45px rgba(15, 23, 42, 0.15);
        }

        body {
            margin: 0;
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(180deg, #f5f7ff 0%, #e2e8fb 60%, #dbeafe 100%);
            color: #0f172a;
            min-height: 100vh;
        }

        .container { min-height: 100vh; }
        .main-content { margin-left: var(--sidebar-width); padding: clamp(1.25rem, 3vw, 2.75rem); display: flex; justify-content: center; }
        .content-wrapper { width: 100%; max-width: 1260px; display: flex; flex-direction: column; gap: 1.5rem; }
        
        .page-header {
            background: #ffffff;
            border-radius: var(--card-radius);
            padding: 1.35rem 1.75rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .page-title-block { display: flex; align-items: flex-start; gap: 1rem; }
        .page-icon { width: 60px; height: 60px; border-radius: 1rem; background: linear-gradient(135deg, #1a3edc, #0b2c73); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2rem; box-shadow: 0 15px 35px rgba(15, 23, 42, 0.25); }
        .page-title-block h1 { margin: 0; font-size: clamp(1.8rem, 2.2vw, 2.3rem); font-weight: 700; }
        .page-title-block .page-description { margin: 0.25rem 0 0; color: #475569; font-weight: 500; line-height: 1.4; }

        .alert { padding: 1rem 1.25rem; border-radius: 0.75rem; margin-bottom: 1rem; }
        .alert-success { background: rgba(59, 130, 246, 0.1); border-left: 4px solid #1a3edc; color: #0f172a; }
        .alert-error { background: rgba(248, 113, 113, 0.1); border-left: 4px solid #ef4444; color: #991b1b; }

        .btn { padding: 12px 24px; border: none; border-radius: 0.75rem; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; color: #fff; }
        .btn-primary { background: linear-gradient(135deg, #1a3edc, #0b2c73); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(15, 23, 42, 0.25); }
        .btn-success { background: #10b981; }
        .btn-danger { background: #ef4444; }

        .card { background: #fff; border-radius: var(--card-radius); box-shadow: var(--card-shadow); }
        .card-header { padding: 1.25rem 1.5rem; background: linear-gradient(135deg, #0c1a33 0%, #1a3edc 100%); color: #f4f6ff; border-radius: var(--card-radius) var(--card-radius) 0 0; }

        @media (max-width: 768px) {
            .sidebar { position: relative; width: 100%; }
            .main-content { margin-left: 0; padding: 1rem; }
            .page-header { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">🏢</div>
                <div>
                    <div class="brand-name">Romar</div>
                    <div class="brand-subtitle">Dormitory</div>
                </div>
            </div>

            <div class="nav-wrapper">
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li class="menu-section">การจัดการ</li>
                    <li><a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a></li>
                    <li><a href="documents.php">📄 จัดการเอกสาร</a></li>
                    <li class="menu-section">ฟีเจอร์</li>
                    <li><a href="room-booking.php">📅 จองห้องประชุม</a></li>
                    <li><a href="announcements.php">📢 ข่าวสาร</a></li>
                    <li><a href="../modules/tickets.php">🎫 IT Tickets</a></li>
                    <li class="menu-section">ระบบ</li>
                    <li><a href="settings.php">⚙️ ตั้งค่า</a></li>
                    <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a></li>
                </ul>
            </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="page-title-block">
                        <div class="page-icon">📊</div><!-- Change emoji for each page -->
                        <div>
                            <h1>Page Title Here</h1>
                            <p class="page-description">Brief page description</p>
                        </div>
                    </div>
                </div>

                <!-- Page content goes here -->

            </div>
        </div>
    </div>
</body>
</html>
```

---

## Files Completed by meeting-rooms.php
✅ Blue gradient sidebar (uses admin-theme.css)
✅ Modern page header with icon
✅ Gradient buttons (primary, secondary, success, danger)
✅ Alert styling (success/error)
✅ Modal styling with blue headers
✅ Card styling with modern shadows
✅ Responsive mobile design
✅ Form controls with focus states

---

## Quick Reference

### Page Icons for Each Module
- Dashboard: 📊
- Settings: ⚙️  
- Room Booking: 📅
- Meeting Rooms: 🏢
- Announcements: 📢
- Documents: 📄
- Tickets: 🎫

### Color Scheme
- Primary Gradient: `linear-gradient(135deg, #1a3edc, #0b2c73)` (Navy Blue)
- Success: `#10b981` (Green)
- Danger: `#ef4444` (Red)
- Text Dark: `#0f172a`
- Background: `linear-gradient(180deg, #f5f7ff 0%, #e2e8fb 60%, #dbeafe 100%)`

### BT Classes to Use
- `.btn` - Base button
- `.btn-primary` - Blue gradient
- `.btn-success` - Green
- `.btn-danger` - Red
- `.btn-secondary` - Gray
- `.alert-success` - Blue accent
- `.alert-error` - Red accent

---

## Next Steps

1. Use `meeting-rooms.php` as the working reference template
2. Apply the same structure to remaining 5 files
3. Save as `.php` files with proper UTF-8 encoding
4. Test responsive design on mobile

---

Created: April 3, 2026
Application: Romar Dormitory Management System
