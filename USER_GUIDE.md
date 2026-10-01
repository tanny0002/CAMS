# CAMS — User Guide

Local demo guide (PHP on your PC + Supabase database).

## Start the app (local)

```powershell
cd D:\Projects\Repo\Cybersecurity-Awareness-Management-System
$env:Path = [System.Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [System.Environment]::GetEnvironmentVariable('Path','User')
php -c .\.php.ini database/migrate_auth.php
php -c .\.php.ini database/migrate_access.php
php -c .\.php.ini -S 127.0.0.1:8080
```

Open: **http://127.0.0.1:8080/** (use `127.0.0.1`, not `localhost`, for Google sign-in).

`.env` should already point at Supabase (`DB_DRIVER=pgsql`). Keep `CAMS_APP_URL=http://127.0.0.1:8080`.

## Default admin

| Email | Temporary password |
|-------|--------------------|
| chitaurotanaka00@gmail.com | Admin@CAMS1 |

On first login you **must change the password**, then **complete your profile**.

## Demo walkthrough (recommended order)

### A. Admin
1. Log in → set new password → complete profile.  
2. **Invite lecturers** (`Admin → Invite lecturers`): pick courses, send email (24h link).  
3. **Approvals** (`Admin → Approvals`): approve individuals / final student course access.  
4. Optional: manage modules, lessons, quizzes, tips, CSV reports.

### B. Lecturer
1. Open invitation link → register/sign in with the invited email → complete profile.  
2. **Invite students** (broadcast emails + courses).  
3. **Approvals**: approve student course requests (then admin confirms).

### C. Student (invited)
1. Accept invitation → register → complete profile → courses unlocked from invite.

### D. Student / individual (no invite)
1. Register → complete profile → select courses.  
2. Student: lecturer then admin approval. Individual: admin approval + course grant.  
3. Study lessons → quizzes → progress → certificate when module rules are met.

### E. Social sign-in
- **Google**: browser button (works even if PHP cannot reach Google APIs).  
- **GitHub**: redirect OAuth.  
- Incomplete profiles always go to **Complete profile** before the app.

## Screenshot checklist
- [ ] Landing page  
- [ ] Login / Register / OTP  
- [ ] Force password + complete profile  
- [ ] Admin invite lecturer + Approvals  
- [ ] Lecturer invite students + Approvals  
- [ ] Lesson reader (sidebar + prose)  
- [ ] Quiz attempt / result  
- [ ] Progress / certificate  
- [ ] Reports CSV  

## Password reset
**Forgot password** sends a branded SMTP email (no on-screen demo tokens).
