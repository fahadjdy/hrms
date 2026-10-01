# Work Shifts, Weekly Holidays aur Holidays

Ye module batata hai ki company me **kaam ka time kya hai** aur **kaun se din chhutti ke hain**.
Attendance, short hours, overtime aur salary — sab isi par chalte hain. Isliye ise sabse pehle sahi set kar lena chahiye.

Is guide me teen cheezein hain:

1. **Work Shifts** — kaam ka time (kab se kab tak, kitne ghante zaroori).
2. **Weekly Holidays** — hafte ke wo din jab company band rehti hai (jaise Sunday).
3. **Holidays** — saal ki chhuttiyan (jaise Diwali, 26 January).

---

## Kahan milega

| Kaam                            | Menu                                                                                           |
| ------------------------------- | ---------------------------------------------------------------------------------------------- |
| Shift banana / badalna          | **Attendance → Work Shifts** (yahi screen **Settings → Work Shifts** se bhi khulti hai)        |
| Company ki default shift chunna | **Settings → Attendance** (yahi screen **Attendance → Attendance Settings** se bhi khulti hai) |
| Kisi ek employee ki alag shift  | Employee ki profile → **Work timing** card → **Change**                                        |
| Hafte ki chhutti                | **Attendance → Weekly Holidays**                                                               |
| Saal ki chhuttiyan              | **Attendance → Holidays**                                                                      |

## Kaun use kar sakta hai

| Kaam                                                                          | Kaun kar sakta hai (shuru me)     |
| ----------------------------------------------------------------------------- | --------------------------------- |
| Shifts, weekly holidays aur holidays **dekhna**                               | Company Admin, HR Manager, Viewer |
| Shift, weekly holiday, holiday **banana / badalna / delete karna**            | Company Admin, HR Manager         |
| Company default shift aur gender-based shift **chunna** (Attendance settings) | Sirf Company Admin                |
| Kisi employee ki alag shift lagana                                            | Company Admin, HR Manager         |

> Company Admin chahe to **Settings → Roles & Permissions** se ye adhikar badal sakta hai. Agar aapko koi button nahi dikh raha, to aapke role me wo adhikar nahi hai.

---

## Sahi shift kaise chuni jaati hai (sabse zaroori baat)

Har employee ke liye system **is kram me** shift dhoondhta hai. Jo pehle mil jaye, wahi lagti hai:

1. **Employee-specific** — agar us employee ki profile par alag shift lagayi gayi hai.
2. **Gender-based** — agar company ne Male / Female / Other staff ke liye alag shift rakhi hai.
3. **Company default** — baaki sab ke liye.

Employee ki profile par **Work timing** card me ek chhota label bhi dikhta hai jo batata hai ki shift kahan se aayi:

| Label                            | Matlab                                        |
| -------------------------------- | --------------------------------------------- |
| **Set for this employee**        | Is employee ke liye alag se lagayi gayi shift |
| **Gender-based company default** | Company ki gender wali shift                  |
| **Company default shift**        | Company ki aam shift                          |

> Zyadatar companies ko sirf **Company default shift** chahiye. Gender-based aur employee-specific shift tabhi lagayein jab sach me time alag ho.

---

# Bhaag 1: Work Shifts

## Screen par kya dikhta hai

**Work Shifts** screen par saari shifts ki list hoti hai:

| Column                 | Matlab                                                                                                                                                 |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Shift**              | Shift ka naam. Saath me label dikh sakta hai: **Company default**, **Male staff**, **Female staff**, **Other staff** — yani ye shift kiski default hai |
| **Timing**             | Shuru aur khatam hone ka time, jaise 9:00 AM to 6:00 PM                                                                                                |
| **Required hours**     | Din me kitna kaam zaroori hai, jaise 8h 00m                                                                                                            |
| **Break**              | Bina salary wala break, jaise 1h 00m. Break ka time rakha ho to wo bhi dikhta hai, jaise 1:00 PM to 2:00 PM (1h 00m)                                   |
| **Assigned employees** | Abhi kitne employees par ye shift alag se lagi hai                                                                                                     |
| **Status**             | **Active** ya **Inactive**                                                                                                                             |

Upar daayi taraf **Add work shift** button hai. Har row ke saamne pencil (edit) aur dustbin (delete) ke nishan hain.

> Nayi company me ek shift pehle se bani hoti hai: **General Shift**, 9:00 AM to 6:00 PM, 8 ghante zaroori, 1 ghanta break. Aap ise badal sakte hain.

## Kaam kaise karein

### Nayi shift banana

1. **Attendance → Work Shifts** kholein.
2. **Add work shift** dabayein.
3. Form bharein:

    | Field                     | Kya bharna hai                                                                 |
    | ------------------------- | ------------------------------------------------------------------------------ |
    | **Shift name**            | Naam, jaise "Morning Shift". Ek naam do baar nahi ho sakta                     |
    | **Start time**            | Shift shuru hone ka time                                                       |
    | **End time**              | Shift khatam hone ka time                                                      |
    | **Break time** (optional) | Break kab se kab tak, jaise 1:00 PM se 2:00 PM. Khali bhi chhod sakte hain     |
    | **Required working time** | **hours** aur **minutes** me — employee ko din me kitna kaam karna zaroori hai |
    | **Active**                | On rakhein agar shift use honi hai                                             |

4. Neeche ek line apne aap hisaab dikhati hai:
    - Break time **khali** ho to: _9h 00m shift - 8h 00m required = 1h 00m break_. Yani shift ke total time me se zaroori kaam ghata kar jo bachta hai, wo **break** hai.
    - Break time **bhara** ho to: _9h 00m shift - 1h 00m break = 8h 00m at work_. Required working time isse zyada nahi ho sakta.
5. **Add work shift** dabayein.

### Break ka time tay karna

**Break time** me **From** aur **To** bharne se break ek tay samay par hota hai, jaise lunch 1:00 PM se 2:00 PM.

- Break shift ke andar hona chahiye. Dono time bharein, ya dono khali chhodein.
- Employee jitni der break ke samay office me tha, sirf utna hi uske kaam ke time se kat-ta hai:

    | Employee kab aaya / gaya (shift 9 se 6, break 1 se 2) | Break kitna kata | Kaam ka time |
    | ----------------------------------------------------- | ---------------- | ------------ |
    | 9:00 AM se 6:00 PM                                    | 1 ghanta         | 8 ghante     |
    | 9:00 AM se 1:00 PM (break se pehle chala gaya)        | Kuchh nahi       | 4 ghante     |
    | 1:30 PM se 6:00 PM (break ke beech aaya)              | 30 minute        | 4 ghante     |

- Break time **khali** ho to purana tarika chalta hai: shift ka bacha hua time break mana jata hai, aur wo tabhi kat-ta hai jab employee aadhi shift se zyada ruka ho.

**Raat ki shift:** agar End time, Start time se pehle ka hai (jaise 10:00 PM se 6:00 AM), to system samajh leta hai ki shift aadhi raat ke baad khatam hoti hai.

### Shift badalna

1. Shift ki row me pencil ka nishan dabayein.
2. Jo badalna hai badlein aur **Save changes** dabayein.

### Shift band karna (Inactive)

1. Shift ko edit karein.
2. **Active** ko off karein aur **Save changes** dabayein.

Inactive shift nayi jagah chunne ki list me nahi aati. Lekin jin par wo pehle se lagi hai, un par tab tak chalti rehti hai jab tak aap unki shift badal nahi dete.

### Shift delete karna

1. Dustbin ka nishan dabayein.
2. **Delete work shift** se pakka karein.

Jo shift kisi ki default hai ya kisi employee par kabhi lagayi gayi hai, wo **delete nahi hoti**. Aisi shift ko **Inactive** kar dein.

### Company ki default shift chunna

1. **Settings → Attendance** kholein.
2. **Work timing** hisse me **Company default shift** chunein. (Ye zaroori hai.)
3. **Save attendance settings** dabayein.

### Gender ke hisaab se alag shift (zaroorat ho tabhi)

1. **Settings → Attendance → Work timing** me **Gender-based default timing** dekhein.
2. **Male staff**, **Female staff** ya **Other staff** me se jiske liye alag time hai, uski shift chunein.
3. Jiske liye alag time nahi hai, use **Company default** par hi rehne dein.
4. **Save attendance settings** dabayein.

Gender employee ki profile se liya jata hai. Agar kisi employee ka gender bhara hi nahi hai, to us par company default shift lagti hai.

### Kisi ek employee ki alag shift lagana

**Naya employee jodte samay:** **Add Employee** form me **Work timing** hisse me **Employee-specific work shift** chunein. Agar alag time nahi hai to ise **Use the default shift (gender or company)** par hi chhod dein.

**Baad me badalna:**

1. Employee ki profile kholein.
2. **Work timing** card me **Change** dabayein.
3. **Change work timing** me:
    - **Work shift** — nayi shift chunein. Default par wapas jaana ho to **Use the default shift (gender or company)** chunein.
    - **Effective from** — kis tareekh se ye lagu hogi.
4. **Save work timing** dabayein.

Purani tareekhon par wahi time rehta hai jo us samay tha. Yani pichhle mahine ki attendance aur salary par asar nahi padta.

---

# Bhaag 2: Weekly Holidays

## Screen par kya dikhta hai

**Weekly Holidays** screen par **Weekly off days** naam ka card hai. Isme Monday se Sunday tak saat din hain, har din ke saamne ek switch:

- Switch **on** → us din ke neeche **Weekly off** likha aata hai.
- Switch **off** → **Working day** likha aata hai.

Card ke upar ek line hoti hai, jaise _Weekly off: Sunday_.

Daayi taraf ek box hai: **Working days in (mahina)** — is mahine me kitne kaam ke din hain (weekly off aur holidays hata kar).

## Kaam kaise karein

### Hafte ki chhutti set karna

1. **Attendance → Weekly Holidays** kholein.
2. Jis din company band rehti hai, uska switch on karein. Jaise Sunday, ya Saturday aur Sunday dono.
3. Switch badalte hi _You have unsaved changes._ likha aata hai.
4. **Save weekly holidays** dabayein.

Kam se kam ek din kaam ka hona zaroori hai — saaton din chhutti nahi rakh sakte.

> Nayi company me **Sunday** pehle se weekly off hota hai.

---

# Bhaag 3: Holidays

## Screen par kya dikhta hai

**Holidays** screen par ek saal ki chhuttiyon ki list hoti hai.

- Upar **saal** likha hota hai, dono taraf teer ke button — pichhla ya agla saal dekhne ke liye.
- Saath me likha hota hai, jaise _12 holidays in 2026, 3 still to come_.
- Daayi taraf **Add holiday** button.

| Column          | Matlab                                                                                |
| --------------- | ------------------------------------------------------------------------------------- |
| **Holiday**     | Chhutti ka naam. Aaj ki chhutti par **Today**, beet chuki par **Past** likha aata hai |
| **Date**        | Tareekh                                                                               |
| **Day**         | Hafte ka din                                                                          |
| **Type**        | Chhutti ka prakar                                                                     |
| **Description** | Koi note (phone par ye column nahi dikhta)                                            |

## Kaam kaise karein

### Chhutti jodna

1. **Attendance → Holidays** kholein.
2. Sahi saal par jaayein (teer wale button se).
3. **Add holiday** dabayein.
4. Form bharein:

    | Field            | Kya bharna hai                                                             |
    | ---------------- | -------------------------------------------------------------------------- |
    | **Holiday name** | Jaise "Diwali"                                                             |
    | **Date**         | Chhutti ki tareekh                                                         |
    | **Holiday type** | **Public Holiday**, **Company Holiday**, **Optional Holiday** ya **Other** |
    | **Description**  | Chahein to chhota note                                                     |

5. **Add holiday** dabayein.

**Holiday type ka matlab:** ye sirf pehchaan ke liye hai. Charon prakar ki chhutti system me ek jaisi maani jaati hai — us din kisi ki absent nahi lagti. **Optional Holiday** bhi sabke liye chhutti hi maani jaati hai.

**Do din ki chhutti:** har tareekh ke liye alag entry banayein (jaise Diwali ke do din ke liye do entries).

### Chhutti badalna ya hatana

- **Badalna:** pencil ka nishan → badlav karein → **Save changes**.
- **Hatana:** dustbin ka nishan → **Delete holiday**. Wo tareekh phir se aam kaam ka din ban jaati hai (agar wo weekly off nahi hai).

---

## Example

**Sharma Traders** me:

- Sab log 9:00 AM se 6:00 PM aate hain, 8 ghante kaam, 1 ghanta lunch. → **General Shift** ko **Company default shift** rakha.
- Mahila staff ka time company niyam se 9:30 AM se 5:30 PM hai. → Nayi shift "Ladies Shift" banayi (7 ghante zaroori, 1 ghanta break) aur **Female staff** me chun li.
- Ramesh (security guard) raat 10:00 PM se subah 6:00 AM aata hai. → "Night Shift" banayi aur Ramesh ki profile par **Change work timing** se 01-10-2026 se laga di.
- Sunday band rehta hai. → **Weekly Holidays** me Sunday on.
- 2 October 2026 ko Gandhi Jayanti. → **Holidays** me "Gandhi Jayanti", type **Public Holiday**.

Ab:

- Priya (female) par "Ladies Shift" lagegi, kyunki uski apni alag shift nahi hai lekin gender wali hai.
- Ramesh par "Night Shift" lagegi, kyunki employee-specific shift sabse pehle dekhi jaati hai.
- Baaki sab par "General Shift".
- 2 October ko kisi ki absent nahi lagegi, aur October ke kaam ke dino me wo din nahi gina jayega.

---

## Dhyan rakhne wali baatein

- **Shift ka asar sirf time par nahi, paise par bhi hai.** Required hours se hi short hours, overtime aur ek ghante ki salary ka hisaab banta hai.
- **Required working time** kam se kam 30 minute hona chahiye aur shift ke total time se zyada nahi ho sakta.
- **Start time aur End time ek jaise nahi ho sakte.**
- **Weekly off aur holiday ko kabhi absent nahi gina jata.** Ye din kaam ke dino me bhi nahi gine jaate.
- **Ek tareekh par ek hi holiday** ho sakta hai.
- **Chhutti tareekh aane se pehle jod dein.** Agar us din ki attendance pehle se save ho chuki hai (jaise Present), to wo record waise hi rehta hai. Aise me us din ko employee ke attendance calendar me kholkar **Clear record** karein — phir wo din Holiday dikhega. Tarika [Attendance guide](05-attendance.md) me hai.
- **Finalized payroll nahi badalta.** Shift, weekly off ya holiday badalne ka asar aage ke dino par aur dobara calculate hone wale payroll par padta hai. Jo payroll finalize ho chuka hai, uski rakam wahi rehti hai.
- **Shift ya chhutti ka har badlav record hota hai** aur **Audit Logs** me dikhta hai.

**Phone par:** shifts aur holidays ki list table ki jagah card ke roop me dikhti hai — har shift/holiday ka ek card. Weekly holidays ke switch ek ke neeche ek aa jaate hain.

---

## Aksar pooche jane wale sawal

**Shift delete nahi ho rahi, kya karein?**
Wo shift kisi ki default hai ya kisi employee par lagi/lagi thi. Use delete nahi kar sakte. Edit karke **Active** off kar dein.

**Maine shift ka time badla, kya purani attendance badal jayegi?**
Jo attendance pehle se save hai, wo waise hi rehti hai. Naya time aage mark hone wale dino par lagta hai.

**Ek employee ko sirf kuch mahino ke liye alag shift deni hai. Kaise?**
Profile par **Change work timing** se shift lagayein aur **Effective from** me shuru ki tareekh dein. Jab wapas aam shift par laana ho, phir **Change** dabakar **Use the default shift (gender or company)** chunein aur nayi tareekh dein.

**Kisi employee par "No shift applies" likha aa raha hai.**
Company ki default shift chuni nahi gayi hai. Company Admin **Settings → Attendance** me **Company default shift** chun kar save kare.

**Saturday aadha din hota hai. Kaise set karein?**
Weekly off poore din ka hota hai, aadhe din ka nahi. Saturday ko working day hi rakhein. Jin logon ka Saturday ko chhota time hai, unki attendance me us din ka asli check-in/check-out bharein, ya zaroorat ho to **Half Day** mark karein.

**Agle saal ki chhuttiyan abhi jod sakte hain?**
Haan. Holidays screen par teer se agla saal kholein aur **Add holiday** se jod dein.

---

Aage padhein: [Attendance](05-attendance.md) · [Leave](06-leave.md) · [Short Hours aur Overtime](07-short-hours-aur-overtime.md) · [Company Settings](13-company-settings.md)
