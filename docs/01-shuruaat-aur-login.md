# Shuruaat aur Login

Ye guide batati hai ki HRMS me login kaise karna hai, kaun sa user kya kar sakta hai, menu kaise bana hua hai, aur apna password, two-factor aur screen ka look kaise badalna hai. Agar aap pehli baar system khol rahe hain to yahin se shuru karein.

Yaad rakhein: ye system sirf Admin aur HR ke liye hai. Employees ka koi login nahi hota. Employee sirf ek record hai jise aap manage karte hain.

## Kahan milega

- **Login screen**: aapki company ka HRMS address browser me kholte hi sabse pehle yahi screen aati hai.
- **Apni settings**: login ke baad left menu (sidebar) me sabse neeche apne naam par click karein, phir **Settings** chunein. Andar teen hisse hain: **Profile**, **Security** aur **Appearance**.
- **Log out**: usi jagah, apne naam par click karke **Log out**.

## Kaun use kar sakta hai

Har user ko ek role diya jata hai. Role se tay hota hai ki menu me kya dikhega aur kaun sa button milega. Jo cheez aapke role me nahi hai, wo menu me dikhti hi nahi.

| Role              | Kya kar sakta hai                                                                                                                                                                                                                                                                                                                                      |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Super Admin**   | Poore platform ka malik. Companies banata hai, unhe active / inactive karta hai aur zaroorat par kisi company ke user ki tarah sign in kar sakta hai. Iske menu me sirf **Companies** aur **Platform Settings** hote hain. Poori jaankari: [Super Admin](14-super-admin.md)                                                                            |
| **Company Admin** | Apni company me sab kuch: employees, attendance, leave, payroll, borrow, final settlement, reports, audit logs, company ki saari settings, aur naye users aur roles banana.                                                                                                                                                                            |
| **HR Manager**    | Roz ka poora HR kaam: employees add / edit / exit, attendance, leave, payroll chalana aur finalize karna, borrow, overtime, bonus, deduction, final settlement, reports aur audit logs. Sirf do cheezein nahi kar sakta: company ki settings (**Company**, **Attendance**, **Payroll**) badalna, aur **Roles & Permissions** me users ya roles banana. |
| **Viewer**        | Sirf dekh sakta hai: employees, attendance, leave, payroll aur salary, borrow / overtime / bonus / deduction, aur reports. Kuch bhi add, edit ya delete nahi kar sakta. **Final Settlement** aur **Audit Logs** iske menu me nahi aate.                                                                                                                |

Kuch aur baatein:

- **Dashboard** company ke har user ko dikhta hai.
- Company Admin chahe to apne hisaab se naye roles bhi bana sakta hai (jaise "Accounts" jo sirf payroll dekhe). Ye [Company Settings](13-company-settings.md) me samjhaya gaya hai.
- Account khud se nahi banta. Login screen par likha hota hai: "Accounts are created by your company administrator." Company ka pehla Company Admin, Super Admin banata hai. Baaki users Company Admin banata hai.

## Screen par kya dikhta hai

### Login screen

- Heading **Log in to your account**
- **Email address** aur **Password** ke box
- **Remember me** ka checkbox
- **Log in** button
- **Forgot your password?** link
- Agar aapka browser support karta hai to upar **Sign in with a passkey** button bhi dikhega

### Login ke baad

- **Left side me menu (sidebar)**: sabse upar logo, beech me saare modules, sabse neeche aapka naam.
- **Upar ki patti**: sabse left me ek chhota button jo menu ko chhota / bada karta hai, aur uske baad likha hota hai ki aap abhi kis page par hain.
- **Beech me page**: jo module aapne khola hai.

Menu me jis heading ke saath teer (arrow) bana hai, us par click karne se uske andar ke pages khulte hain.

| Menu                 | Andar ke pages                                                                                                                   | Guide                                                                                                              |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| **Dashboard**        | -                                                                                                                                | [Dashboard](02-dashboard.md)                                                                                       |
| **Employees**        | **Active Employees**, **Past Employees**, **Add Employee**, **Departments**, **Designations**, **Salary History**, **Documents** | [Employees](03-employees.md)                                                                                       |
| **Attendance**       | **Daily Attendance**, **Attendance Calendar**, **Work Shifts**, **Attendance Settings**, **Weekly Holidays**, **Holidays**       | [Attendance](05-attendance.md), [Work Shifts aur Holidays](04-work-shifts-aur-holidays.md)                         |
| **Leave**            | **Leave Records**, **Leave Types**, **Leave Balance**                                                                            | [Leave](06-leave.md)                                                                                               |
| **Payroll**          | **Payroll**, **Salary Structure**, **Salary Revisions**, **Salary Slips**, **Payroll Reports**                                   | [Payroll aur Salary Slip](10-payroll-aur-salary-slip.md), [Salary, Bonus, Deduction](09-salary-bonus-deduction.md) |
| **Employee Finance** | **Borrow / Advance**, **Borrow Recovery**, **Overtime**, **Short Hours**, **Deductions**, **Bonuses**                            | [Borrow / Advance](08-borrow-advance.md), [Short Hours aur Overtime](07-short-hours-aur-overtime.md)               |
| **Final Settlement** | -                                                                                                                                | [Employee Exit aur Final Settlement](11-employee-exit-aur-final-settlement.md)                                     |
| **Reports**          | -                                                                                                                                | [Reports aur Audit Log](12-reports-aur-audit-log.md)                                                               |
| **Settings**         | **Company**, **Attendance**, **Payroll**, **Leave**, **Work Shifts**, **Roles & Permissions**                                    | [Company Settings](13-company-settings.md)                                                                         |
| **Audit Logs**       | -                                                                                                                                | [Reports aur Audit Log](12-reports-aur-audit-log.md)                                                               |

Aapke role ke hisaab se inme se kuch cheezein kam dikh sakti hain.

## Kaam kaise karein

### Login karna

1. Browser me apni company ka HRMS address kholein.
2. **Email address** me apna email likhein.
3. **Password** me apna password likhein.
4. Agar ye aapka apna computer hai aur aap baar baar login nahi karna chahte to **Remember me** par tick karein.
5. **Log in** dabayein.
6. Company ke user ko **Dashboard** dikhega. Super Admin ko **Companies** ki list dikhegi.

### Password bhool gaye ho to

1. Login screen par **Forgot your password?** par click karein.
2. **Email address** me apna login wala email likhein.
3. **Email password reset link** dabayein.
4. Apna email kholein. Usme aaye link par click karein.
5. **Reset password** screen khulegi. **Password** aur **Confirm password** me naya password likhein (dono me same).
6. **Reset password** dabayein. Ab naye password se login karein.

Email na aaye to spam folder dekhein. Phir bhi na mile to apne Company Admin se baat karein.

### Apna naam ya email badalna

1. Menu me neeche apne naam par click karein, phir **Settings**.
2. **Profile** kholein.
3. **Name** ya **Email address** badlein.
4. **Save** dabayein.

Email badalne par system naye email par ek verification email bhej sakta hai. Us email me diye link par click karke email verify kar lein.

### Password badalna

1. **Settings** me **Security** kholein.
2. **Update password** hisse me **Current password** me purana password likhein.
3. **New password** aur **Confirm password** me naya password likhein.
4. **Save** dabayein.

### Two-factor authentication (2FA) on karna

2FA on karne ke baad login ke waqt password ke saath phone ke authenticator app ka 6 ank ka code bhi maanga jata hai. Isse account zyada surakshit rehta hai.

1. **Settings** me **Security** kholein aur **Two-factor authentication** hisse tak jayein.
2. **Enable 2FA** dabayein. System ek baar aapka password dobara pooch sakta hai.
3. Screen par ek QR code aayega. Apne phone ke authenticator app se use scan karein. Scan na ho to "or, enter the code manually" ke neeche diya code app me haath se daal dein.
4. **Continue** dabayein.
5. App me dikh raha 6 ank ka code likhein aur **Confirm** dabayein.
6. Ab **2FA recovery codes** hisse me **View recovery codes** dabakar codes dekh lein aur kisi surakshit jagah likh kar rakhein.

Band karna ho to usi jagah **Disable 2FA** dabayein.

### 2FA ke saath login karna

1. Email aur password daal kar **Log in** dabayein.
2. **Authentication code** screen aayegi. Phone ke app ka 6 ank ka code likhein aur **Continue** dabayein.
3. Phone paas me nahi hai to neeche "login using a recovery code" par click karein, ek recovery code likhein aur **Continue** dabayein.

### Passkey se login (bina password ke)

Passkey ka matlab hai apne phone ya computer ke fingerprint, face ya PIN se login karna.

1. **Settings** me **Security** kholein aur **Passkeys** hisse tak jayein.
2. **Add passkey** dabayein.
3. **Passkey name** me pehchaan ke liye naam likhein (jaise "Office laptop") aur **Register passkey** dabayein.
4. Apne device par fingerprint / face / PIN se confirm karein.
5. Agli baar login screen par **Sign in with a passkey** dabayein.

### Screen ka look badalna (light / dark)

1. **Settings** me **Appearance** kholein.
2. **Light**, **Dark** ya **System** me se ek chunein. **System** ka matlab hai jaisa aapke computer ya phone me set hai waisa.

### Log out karna

1. Menu me sabse neeche apne naam par click karein.
2. **Log out** chunein.

### Menu ko chhota ya bada karna

Upar ki patti me sabse left wale button par click karein. Menu sirf icons tak chhota ho jata hai aur page ko zyada jagah milti hai. Dobara click karne par poora menu wapas aa jata hai.

## Example

Meera Joshi, Acme Traders me HR Manager hain. Subah wo browser me HRMS kholti hain, **Email address** me apna office email aur **Password** likh kar **Log in** dabati hain. Unke phone ke authenticator app me code 482 913 dikh raha hai, wo use **Authentication code** screen par likh kar **Continue** dabati hain. Dashboard khul jata hai.

Unke menu me **Employees**, **Attendance**, **Leave**, **Payroll**, **Employee Finance**, **Final Settlement**, **Reports** aur **Audit Logs** sab dikhte hain. **Settings** ke andar unhe sirf **Leave** aur **Work Shifts** dikhte hain, kyunki company ki baaki settings sirf Company Admin badal sakta hai.

Usi company ke Vivek Anand ka role Viewer hai. Wo wahi pages khol kar dekh sakte hain, lekin unhe **Add employee** jaise button nahi dikhte.

## Dhyan rakhne wali baatein

- **Employee login nahi karta.** Employees ke liye koi alag screen ya app nahi hai.
- **Account sirf admin banata hai.** Khud se register karne ka koi tareeka nahi hai.
- **Band kiya gaya user login nahi kar sakta.** Agar Company Admin ne aapka account inactive kar diya hai, ya Super Admin ne poori company ko inactive kar diya hai, to sahi password ke baad bhi login nahi hoga.
- **Remember me** sirf apne personal computer par use karein. Shared computer par kaam ke baad hamesha **Log out** karein.
- **Recovery codes sambhal kar rakhein.** Har code sirf ek baar chalta hai. Phone kho jaye aur recovery code bhi na ho to login nahi ho payega. Naye codes ke liye **Regenerate codes** dabayein.
- Aap jo bhi badlav karte hain (attendance, salary, borrow, payroll), wo aapke naam ke saath **Audit Logs** me darj hota hai.
- Apna account aap khud delete nahi kar sakte. Zaroorat ho to Company Admin use inactive karta hai.

**Phone par:** menu chhupa rehta hai. Upar left wale button par tap karne se menu khulta hai, aur kisi page par tap karte hi band ho jata hai. Lambi tables phone par cards ban jaati hain, yaani har row ek alag dabba, taaki side me scroll na karna pade.

## Aksar pooche jane wale sawal

**Mujhe menu me Settings ya Audit Logs kyun nahi dikh raha?**
Kyunki aapke role me uski ijazat nahi hai. Zaroorat ho to Company Admin se apna role badalwayein.

**Kya employee apni attendance ya salary slip khud dekh sakta hai?**
Nahi. Employees ka login nahi hai. Salary slip aap download karke unhe de sakte hain.

**Sahi password daalne par bhi login nahi ho raha, kya karun?**
Pehle email ki spelling dekhein. Phir **Forgot your password?** se naya password banayein. Agar tab bhi na ho to ho sakta hai aapka account ya company inactive ho. Company Admin se poochhein.

**Phone kho gaya aur 2FA on hai. Ab?**
Login ke baad code wali screen par "login using a recovery code" chunein aur apna ek recovery code daalein. Andar aakar **Security** me 2FA dobara set kar lein.

**Kya ek hi email se do companies me login ho sakta hai?**
Nahi. Ek email ek hi user ka hota hai, aur wo user ek hi company se juda hota hai.

**Screen par upar peeli patti dikh rahi hai "You are signed in as ...". Ye kya hai?**
Ye tab dikhti hai jab Super Admin kisi company ke user ki tarah andar aaya ho. Us dauran kiye gaye badlav asli hote hain. **Return to platform admin** dabane se Super Admin wapas apne account me aa jata hai.
