# Company Settings

Yahan aap apni company ke niyam ek baar set karte hain: company ki details, attendance kaise lagegi, salary kaise calculate hogi, aur kaun-kaun login karke kya kar sakta hai. Poora system inhi settings ke hisaab se chalta hai, isliye kaam shuru karne se pehle inhe dhyan se set kar lein.

## Kahan milega

Left menu mein **Settings** group:

| Menu item               | Kya hota hai                                                                |
| ----------------------- | --------------------------------------------------------------------------- |
| **Company**             | Company ka naam, logo, address, currency, date format                       |
| **Attendance**          | Attendance mode, work timing, late aur short hours ke niyam                 |
| **Payroll**             | Salary calculation, overtime, short hours aur borrow ke niyam               |
| **Leave**               | Leave types (dekhein [Leave](06-leave.md))                                  |
| **Work Shifts**         | Shifts (dekhein [Work Shifts aur Holidays](04-work-shifts-aur-holidays.md)) |
| **Roles & Permissions** | Login karne wale users aur unke roles                                       |

Attendance ki settings **Attendance** → **Attendance Settings** se bhi khulti hain. Dono jagah ek hi screen hai.

## Kaun use kar sakta hai

| Screen                                            | Company Admin | HR Manager | Viewer |
| ------------------------------------------------- | ------------- | ---------- | ------ |
| **Company**, **Attendance**, **Payroll** settings | Haan          | Nahi       | Nahi   |
| **Roles & Permissions**                           | Haan          | Nahi       | Nahi   |

Shuru mein ye sab sirf **Company Admin** kar sakta hai. Aap chahein to naya role bana kar kisi aur ko bhi ye haq de sakte hain (neeche "Naya role banana" dekhein).

## Screen par kya dikhta hai

### Company

Teen hisse aur neeche **Save company details** button.

**Company**

| Field                | Kya bharna hai                   |
| -------------------- | -------------------------------- |
| **Company name**     | Company ka naam (zaroori)        |
| **Legal name**       | Registered naam, agar alag ho    |
| **Email**, **Phone** | Company ka contact               |
| **GST / tax number** | GST ya tax number                |
| **Logo**             | JPG, PNG ya WebP photo, 2 MB tak |

Company ka naam aur logo left menu ke upar aur salary slip par dikhte hain.

**Address**: **Address**, **City**, **State**, **Country**, **Postal code**.

**Regional settings**

| Field           | Asar                                                                                                          |
| --------------- | ------------------------------------------------------------------------------------------------------------- |
| **Currency**    | 3 akshar ka code (jaise INR). Poore system mein paisa isi mein dikhega. Neeche example bhi dikhta hai         |
| **Timezone**    | Aapke ilaake ka samay (jaise Asia/Kolkata). Isi se tay hota hai ki "aaj" ka din kab shuru aur khatam hota hai |
| **Date format** | Tareekh kaise dikhe. Dropdown mein aaj ki tareekh paanch tarah se likhi hoti hai, jo pasand ho chun lein      |

### Attendance settings

**Attendance mode** (do mein se ek chunein)

| Option        | Matlab                                                                                                                        |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **Automatic** | Kaam ke din apne aap present lag jaate hain. Aap sirf alag cheezein record karte hain: absent, leave, late aana, short hours  |
| **Manual**    | Har kaam ke din ki attendance aapko khud lagani hai. Jis din ki attendance nahi lagi, payroll mein wo din absent maana jayega |

Dono mode mein weekly off, holiday aur approved leave apne aap bhar jaate hain.

**Work timing**

Kis employee par kaunsi timing lagegi, ye is kram se tay hota hai. Jo pehle mile wahi lagta hai:

1. **Employee-specific**: employee ki profile par set ki gayi shift
2. **Gender-based**: neeche diye gaye optional default
3. **Company default**: baaki sab ke liye

| Field                                             | Kya bharna hai                                                                                                    |
| ------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| **Company default shift**                         | Wo shift jo sab par lagegi (zaroori)                                                                              |
| **Male staff**, **Female staff**, **Other staff** | Sirf tab bharein jab company ke niyam se timing gender ke hisaab se alag ho. Warna **Company default** rehne dein |

Dropdown mein sirf active shifts aati hain. Nayi shift **Work Shifts** screen par banti hai.

**Late and short-hours rules** (ye tab lagte hain jab din ki attendance check-in aur check-out time ke saath lagi ho)

| Field                                    | Matlab                                                                                                             | Limit    |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------ | -------- |
| **Grace period (minutes)**               | Shift shuru hone ke itne minute baad tak aana late nahi gina jata                                                  | 0 se 240 |
| **Late arrivals per half-day deduction** | Itne late din par aadhe din ki salary katti hai. 3 likhne par har 3 late par aadha din. 0 likhne par ye niyam band | 0 se 31  |
| **Short-hours tolerance (minutes)**      | Din itne minute ya usse kam chhota ho to short nahi gina jata                                                      | 0 se 240 |

Neeche **Save attendance settings** button.

### Payroll settings

Upar likha rehta hai: badlav agli baar payroll calculate hone par lagta hai, finalized payroll kabhi nahi badalta.

**Salary calculation method** (ek din ki salary kitni, ye isse tay hota hai. Absent, unpaid leave aur half day isi rate se katte hain)

| Option                         | Matlab                                                                                              | ₹30,000 salary par example    |
| ------------------------------ | --------------------------------------------------------------------------------------------------- | ----------------------------- |
| **Calendar days in the month** | Mahine ki salary ko us mahine ke kul dinon se baanta jata hai. Weekly off aur holiday paid din hain | 30 din ka mahina: ₹1,000 roz  |
| **Working days in the month**  | Salary ko us mahine ke kaam ke dinon se baanta jata hai                                             | 26 kaam ke din: ₹1,153.85 roz |
| **Fixed 30 days**              | Hamesha 30 se baanta jata hai, mahina 28, 30 ya 31 din ka ho                                        | Har mahine ₹1,000 roz         |

**Payroll cycle** (payroll har mahine chalta hai)

| Field                            | Matlab                                                                                                                                                             |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Payroll period starts on day** | Payroll ka mahina kis tareekh se shuru ho (1 se 28). 1 rakhne par poora calendar mahina. 21 rakhne par September ka payroll 21 September se 20 October tak chalega |
| **Salary payment day**           | Agle mahine ki kis tareekh ko salary di jati hai (1 se 28)                                                                                                         |

**Overtime rules**

| Field                                              | Matlab                                                                                                                                                                                                                 |
| -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Overtime rate**                                  | **Hourly salary rate x a multiplier**: employee ki ek ghante ki salary ko multiplier se guna. Ya **A fixed rate per hour**: sab ke liye ek hi rate                                                                     |
| **Multiplier**                                     | 1.5 matlab dedh guna. Ghante ki salary ₹125 ho to overtime ka ek ghanta ₹187.50 (0 se 10 tak)                                                                                                                          |
| **Fixed rate per hour**                            | Fixed rate chunne par, ek ghante ka rate                                                                                                                                                                               |
| **Pay overtime recorded in attendance** (on / off) | On: attendance mein zaroori ghanton se zyada kaam apne aap upar wale rate par pay hota hai (jis tareekh ki overtime entry pehle se hai use chhod kar). Off: sirf wahi overtime pay hota hai jo aap khud add karte hain |

Haath se add ki gayi overtime entry ka apna rate ya fixed amount hota hai.

**Short-hours rule** (jab employee zaroori ghanton se kam kaam kare)

| Option                 | Matlab                                                                                   |
| ---------------------- | ---------------------------------------------------------------------------------------- |
| **Deduct from salary** | Kam ghante x ghante ka rate apne aap salary se kat jata hai                              |
| **Record only**        | Kam ghante reports aur payroll ke hisaab mein dikhte hain, par kuch nahi katta           |
| **Manual adjustment**  | Kuch nahi katta jab tak aap Short Hours screen par us employee ke liye amount na daalein |

Kisi employee ke liye haath se daala gaya amount hamesha in niyamon se upar rehta hai.

| Field                       | Matlab                                                                                            |
| --------------------------- | ------------------------------------------------------------------------------------------------- |
| **One short hour is worth** | **The employee's hourly salary rate** (employee ki ghante ki salary) ya **A fixed rate per hour** |
| **Fixed rate per hour**     | Fixed chunne par, ek kam ghante ka rate. ₹200 ho to 1 ghanta kam hone par ₹200                    |

**Borrow deduction rules**

| Field                                                       | Matlab                                                                                                                                                                                |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Deduct the monthly installment automatically** (on / off) | On: har active borrow ki monthly kist apne start month se payroll mein apne aap judti hai. Off: recovery tabhi hoti hai jab aap payroll mein adjustment ke roop mein daalein          |
| **Maximum recovery (% of pay)**                             | Ek mahine mein kul borrow recovery us mahine ki pay (baaki sab kamai aur katauti ke baad jo bachti hai) ke is percent se zyada nahi hogi (1 se 100). Khali chhodne par koi limit nahi |

Recovery kabhi bhi borrow ke baaki amount se, ya bachi hui salary se, zyada nahi hoti.

Neeche **Save payroll settings** button.

### Roles & permissions

Do hisse hain.

**Users**: aapki company ke wo log jo login kar sakte hain. Table mein **User**, **Email**, **Role**, **Status** (**Active** ya **Deactivated**). Aapke apne naam ke aage "(you)" likha hota hai.

**Roles**: har role permissions ka ek set hai. Table mein **Role**, **Permissions** (jaise "14 of 16" ya "All permissions"), **Users** (kitne users ke paas ye role hai). Pehle se bane roles par **Built-in** likha hota hai.

Pehle se bane teen roles:

| Kaam                                            | Company Admin | HR Manager | Viewer |
| ----------------------------------------------- | ------------- | ---------- | ------ |
| View employees                                  | Haan          | Haan       | Haan   |
| Add, edit and exit employees                    | Haan          | Haan       | Nahi   |
| View attendance                                 | Haan          | Haan       | Haan   |
| Mark and edit attendance                        | Haan          | Haan       | Nahi   |
| View leave                                      | Haan          | Haan       | Haan   |
| Manage leave                                    | Haan          | Haan       | Nahi   |
| View payroll and salary                         | Haan          | Haan       | Haan   |
| Run payroll and revise salary                   | Haan          | Haan       | Nahi   |
| Finalize and reopen payroll                     | Haan          | Haan       | Nahi   |
| View borrow, overtime, bonuses and deductions   | Haan          | Haan       | Haan   |
| Manage borrow, overtime, bonuses and deductions | Haan          | Haan       | Nahi   |
| Manage final settlements                        | Haan          | Haan       | Nahi   |
| View reports                                    | Haan          | Haan       | Haan   |
| Manage company settings                         | Haan          | Nahi       | Nahi   |
| Manage users, roles and permissions             | Haan          | Nahi       | Nahi   |
| View audit logs                                 | Haan          | Haan       | Nahi   |

"View" wali permission se screen khulti hai. "Manage" wali permission se badlav kar sakte hain.

## Kaam kaise karein

### Company ki details aur logo badalna

1. **Settings** → **Company** kholein.
2. Jo field badalni hai badlein. Logo ke liye **Logo** mein nayi photo chunein, bagal mein uska preview dikhega.
3. **Save company details** dabayein.

### Attendance mode chunna

1. **Settings** → **Attendance** kholein.
2. **Attendance mode** mein **Automatic** ya **Manual** card par click karein.
3. **Save attendance settings** dabayein.

### Default shift aur gender-based timing set karna

1. Pehle **Work Shifts** screen par zaroori shifts bana lein.
2. **Settings** → **Attendance** kholein.
3. **Company default shift** mein wo shift chunein jo sab par lage.
4. Agar mahila ya purush staff ki timing company ke niyam se alag hai to **Female staff** / **Male staff** / **Other staff** mein shift chunein. Nahi to **Company default** rehne dein.
5. **Save attendance settings** dabayein.

Kisi ek employee ki alag timing uski profile se set hoti hai. Dekhein [Employees](03-employees.md).

### Late aur short hours ke niyam set karna

1. **Settings** → **Attendance** mein **Late and short-hours rules** tak jayein.
2. **Grace period (minutes)**, **Late arrivals per half-day deduction** aur **Short-hours tolerance (minutes)** bharein.
3. **Save attendance settings** dabayein.

### Salary calculation method chunna

1. **Settings** → **Payroll** kholein.
2. **Salary calculation method** mein teen card mein se ek chunein. Har card par example likha hota hai.
3. **Save payroll settings** dabayein.

### Overtime ka rate set karna

1. **Settings** → **Payroll** mein **Overtime rules** tak jayein.
2. **Overtime rate** chunein. Multiplier chuna to **Multiplier** bharein, fixed chuna to **Fixed rate per hour** bharein.
3. Attendance ke zyada ghante apne aap pay karne hain to **Pay overtime recorded in attendance** on karein.
4. **Save payroll settings** dabayein.

### Short hours ka niyam chunna

1. **Settings** → **Payroll** mein **Short-hours rule** tak jayein.
2. **Deduct from salary**, **Record only** ya **Manual adjustment** chunein.
3. **One short hour is worth** mein rate ka tareeka chunein. Fixed chuna to rate bharein.
4. **Save payroll settings** dabayein.

Short hours ka poora kaam [Short Hours aur Overtime](07-short-hours-aur-overtime.md) mein samjhaya gaya hai.

### Borrow ki katauti ke niyam set karna

1. **Settings** → **Payroll** mein **Borrow deduction rules** tak jayein.
2. Kist apne aap katni chahiye to **Deduct the monthly installment automatically** on rakhein.
3. Limit lagani ho to **Maximum recovery (% of pay)** mein percent likhein.
4. **Save payroll settings** dabayein.

### Naya user jodna (login dena)

1. **Settings** → **Roles & Permissions** kholein.
2. **Users** ke saamne **Add user** dabayein.
3. **Name**, **Email**, **Password** bharein aur **Role** chunein.
4. **Add user** dabayein.
5. Email aur password us vyakti ko bata dein. Wo isi email aur password se login karega.

### User ka role ya password badalna

1. **Users** table mein us user ki line par pencil button dabayein.
2. **Name** ya **Role** badlein. Password badalna ho to **New password** bharein, nahi to khali chhod dein.
3. **Save changes** dabayein.

### User ka login band karna (deactivate)

1. User ki line par pencil button dabayein.
2. **Active** switch band kar dein.
3. **Save changes** dabayein.

Deactivated user dobara login nahi kar sakta. Agar wo us waqt login hai to agli click par bahar ho jayega. Wapas chalu karne ke liye yahi switch on kar dein.

### Naya role banana

1. **Roles** ke saamne **Add role** dabayein.
2. **Role name** likhein (jaise "Payroll Officer").
3. **Permissions** mein jo kaam is role ko karne dene hain un par tick lagayein. Permissions hisson mein bati hain: Employees, Attendance, Leave, Payroll, Finance, Settlements, Reports, Settings, Roles, Audit.
4. **Add role** dabayein.
5. Ab kisi user ko edit karke ye role de dein.

### Role ki permissions badalna ya role delete karna

1. **Roles** table mein role ki line par pencil button dabayein, tick badlein, **Save changes** dabayein.
2. Delete karne ke liye dustbin button dabayein aur **Delete role** se confirm karein.

**Company Admin** role ke saamne "Cannot be changed" likha hota hai, ise na badal sakte hain na delete kar sakte hain. **HR Manager** aur **Viewer** ki permissions badal sakte hain, par inhe delete nahi kar sakte.

### Apna khud ka password badalna

Left menu mein sabse neeche apne naam par click karein → **Settings** → **Security**. Dekhein [Shuruaat aur Login](01-shuruaat-aur-login.md).

## Example

Shree Textiles ki Company Admin Kavita system set kar rahi hain.

1. **Settings** → **Company** mein naam "Shree Textiles", GST number aur logo daalti hain. Currency INR, timezone Asia/Kolkata.
2. **Settings** → **Attendance** mein **Automatic** chunti hain, kyunki zyadatar log roz aate hain. Default shift "General Shift" (9:00 AM se 6:00 PM). **Grace period** 10 minute, **Late arrivals per half-day deduction** 3, **Short-hours tolerance** 15 minute.
    - Rahul 9:08 par aaya: late nahi (10 minute ke andar).
    - Rahul mahine mein 3 baar 9:25 par aaya: aadhe din ki salary kategi.
3. **Settings** → **Payroll** mein **Calendar days in the month** chunti hain. Rahul ki salary ₹30,000 hai, September 30 din ka hai, to ek din ₹1,000 ka. 2 din absent par ₹2,000 katenge.
4. Overtime: **Hourly salary rate x a multiplier**, multiplier 1.5. Rahul ki ghante ki salary ₹125 hai, to overtime ka ek ghanta ₹187.50.
5. Short hours: **Deduct from salary**, rate employee ki ghante ki salary. Rahul 2 ghante kam raha to ₹250 katenge.
6. Borrow: automatic kist on, **Maximum recovery** 40%. Baaki sab kamai aur katauti ke baad Rahul ki is mahine ki pay ₹28,000 bachti hai, to borrow recovery ₹11,200 se zyada nahi hogi.
7. **Roles & Permissions** mein **Add user** se Meera Joshi ko **HR Manager** role ke saath jodti hain, aur accountant ke liye "Payroll Officer" naam ka naya role banati hain jisme sirf payroll aur reports ki permissions hain.

## Dhyan rakhne wali baatein

- Payroll settings ka badlav **agli baar payroll calculate** hone par lagta hai. Jo payroll **Finalized** ho chuka hai wo kabhi nahi badalta.
- Salary calculation method mahine ke beech mein badalne se us mahine ka hisaab badal jayega. Ise saal ya mahine ki shuruaat mein hi tay karein.
- **Manual** attendance mode mein jis din ki attendance nahi lagi wo payroll mein absent ginta hai. Payroll chalane se pehle attendance poori kar lein.
- Late aur short hours ke niyam tabhi lagte hain jab attendance check-in aur check-out time ke saath lagi ho.
- Payroll period aur salary payment ki tareekh 1 se 28 ke beech hi ho sakti hai.
- User ka **email banne ke baad nahi badalta**. Galat email ho to us user ko deactivate karke naya user banayein.
- Ek email sirf ek hi user ke liye use ho sakta hai.
- Password mazboot rakhein: kam se kam 12 akshar, bade aur chhote letter, number aur symbol. Kamzor password system nahi leta.
- Aap **apna khud ka role nahi badal sakte** aur khud ko deactivate nahi kar sakte.
- Company mein hamesha **kam se kam ek active Company Admin** rehna zaroori hai. Aakhri admin ka role badalne ya use deactivate karne se pehle kisi aur ko Company Admin banayein.
- Jo role kisi user ko diya hua hai wo delete nahi hota. Pehle un users ko doosra role dein.
- Users delete nahi hote, sirf deactivate hote hain, taaki purana record (kisne kya kiya) bana rahe.
- **Employees ko kabhi login nahi milta.** Yahan sirf Admin aur HR staff jodein.
- Settings ka har badlav [Audit Logs](12-reports-aur-audit-log.md) mein record hota hai.
- Phone par saari settings ek ke neeche ek aati hain aur users / roles ki table card ban jati hai. Sab kaam phone se bhi ho sakta hai.

## Aksar pooche jane wale sawal

**Automatic aur Manual mein kya chunein?**
Agar zyadatar staff roz samay par aata hai aur aap sirf chhutti ya late record karna chahte hain to **Automatic**. Agar har din ki haazri khud lagana chahte hain (jaise daily wage ya shift wala kaam) to **Manual**.

**Maine settings badli par purani salary slip mein fark nahi aaya, kyun?**
Finalized payroll lock hota hai aur nahi badalta. Nayi settings agle calculate hone wale payroll par lagengi.

**Kaunsa salary calculation method sahi hai?**
Ye aapki company ki policy par hai. **Calendar days** sabse aam hai. **Working days** mein ek din ki katauti zyada hoti hai. **Fixed 30 days** mein har mahine ek din ki keemat barabar rehti hai.

**HR Manager ko settings ya users kyun nahi dikhte?**
Shuru mein ye haq sirf Company Admin ke paas hai. Dena ho to **Roles & Permissions** mein HR Manager role edit karke **Manage company settings** ya **Manage users, roles and permissions** par tick lagayein.

**User apna password bhool gaya, kya karein?**
**Roles & Permissions** mein us user ko edit karke **New password** bharein aur use naya password bata dein.

**Employee ko login kaise dein?**
Nahi de sakte. Ye system sirf Admin aur HR ke liye hai, employees ka koi login nahi hota.
