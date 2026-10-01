# Short Hours aur Overtime

Ye module do ulti cheezon ka hisaab rakhta hai:

- **Short Hours** — jab employee ne zaroori ghanto se **kam** kaam kiya. Company tay karti hai ki iska paisa salary se kate ya nahi.
- **Overtime** — jab employee ne zaroori ghanto se **zyada** kaam kiya. Iska paisa salary me judta hai.

Dono ka asar seedha salary par padta hai, aur dono payroll me **alag line** me dikhte hain taaki har rupaye ka hisaab saaf rahe.

---

## Kahan milega

| Kaam                                              | Menu                               |
| ------------------------------------------------- | ---------------------------------- |
| Kam ghanto ka hisaab dekhna / rakam badalna       | **Employee Finance → Short Hours** |
| Overtime jodna / manzoor karna                    | **Employee Finance → Overtime**    |
| Short-hours ka niyam aur overtime ka rate badalna | **Settings → Payroll**             |
| Ek din me kitni kami maaf hai (tolerance)         | **Settings → Attendance**          |

## Kaun use kar sakta hai

| Kaam                                                                             | Kaun kar sakta hai (shuru me)     |
| -------------------------------------------------------------------------------- | --------------------------------- |
| Short Hours aur Overtime **dekhna**                                              | Company Admin, HR Manager, Viewer |
| Short-hours ki rakam **adjust karna**; overtime **jodna, badalna, delete karna** | Company Admin, HR Manager         |
| Company ka short-hours niyam aur overtime rate **badalna** (Settings)            | Sirf Company Admin                |

> Company Admin **Settings → Roles & Permissions** se ye adhikar badal sakta hai.

---

# Bhaag 1: Short Hours

## Short hours kab bante hain

Short hours **attendance se** apne aap bante hain — unhe alag se jodna nahi padta.

Jab kisi din ki attendance me **check-in aur check-out dono** bhare jaate hain, system employee ki shift se milakar dekhta hai ki kitna kaam hua. Zaroori ghanto se jitna kam hua, wo us din ke short hours hain.

- Agar kisi din time nahi bhara gaya, to wo din poora gina jata hai — short hours nahi bante.
- Agar ek din ki kami **Short-hours tolerance (minutes)** jitni ya usse kam hai, to use nahi gina jata. (Ye **Settings → Attendance** me hota hai.)

Attendance kaise bharte hain, ye [Attendance guide](05-attendance.md) me hai.

## Company ke teen niyam

Company Admin **Settings → Payroll → Short-hours rule** me teen me se ek niyam chunta hai:

| Niyam                  | Kya hota hai                                                                                       |
| ---------------------- | -------------------------------------------------------------------------------------------------- |
| **Deduct from salary** | Kam ghanto ki rakam apne aap salary se kat jaati hai                                               |
| **Record only**        | Kam ghante sirf dikhte hain (yahan, reports me aur payroll ke byore me). Salary se kuch nahi katta |
| **Manual adjustment**  | Jab tak aap kisi employee ke liye khud rakam nahi bharte, kuch nahi katta                          |

**Teeno niyam me ek baat same hai:** agar aap kisi employee ke liye khud rakam bhar dete hain (Adjust), to **wahi rakam katti hai** — niyam chahe jo ho.

> Nayi company me shuru me **Record only** niyam hota hai.

## Rakam ka hisaab kaise banta hai

**Rakam = kam ghante × ek ghante ka rate**

Ek ghante ka rate do me se ek hota hai (**Settings → Payroll → One short hour is worth**):

| Vikalp                                | Matlab                                                                                                           |
| ------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| **The employee's hourly salary rate** | Employee ki apni ek ghante ki salary. Ye uski ek din ki salary ko din ke zaroori ghanto se bhaag dekar banti hai |
| **A fixed rate per hour**             | Sabke liye ek hi tay rate, jaise ₹100 prati ghanta                                                               |

Jaise: ek din ki salary ₹1,200 aur din me 8 ghante zaroori → ek ghante ka rate ₹150. Mahine me 2 ghante 30 minute kam → 2.5 × ₹150 = **₹375**.

## Screen par kya dikhta hai

**Sabse upar ek box** batata hai ki company ka niyam kya hai — jaise _Company rule: Deduct from salary_ — aur saath me rate aur tolerance ki jaankari. Company Admin ko yahan **Change rule** button bhi dikhta hai jo seedha **Settings → Payroll** par le jata hai.

**Uske neeche:** mahina badalne ke teer, employee ka naam ya ID se khoj, aur **Payroll period** — kis tareekh se kis tareekh tak ka hisaab hai.

**List me sirf wo employees aate hain jinka us avadhi me kam se kam ek din short raha.**

| Column                    | Matlab                                                                            |
| ------------------------- | --------------------------------------------------------------------------------- |
| **Employee**              | Naam, ID, department. Click karne par us mahine ka attendance calendar khulta hai |
| **Required hours**        | Poori avadhi me kitna kaam zaroori tha                                            |
| **Actual hours**          | Kitna kaam hua                                                                    |
| **Short hours**           | Kul kitna kam raha                                                                |
| **Hourly rate**           | Ek ghante ka rate                                                                 |
| **Calculated amount**     | Hisaab se bani rakam (kam ghante × rate)                                          |
| **Admin-adjusted amount** | Aapki bhari hui rakam aur uska kaaran. Nahi bhari to **Not adjusted**             |
| **Final deduction**       | Salary se sach me kitna kat raha hai                                              |

**Final deduction** ke neeche ek label hota hai:

| Label              | Matlab                                |
| ------------------ | ------------------------------------- |
| **Deducted**       | Niyam ke hisaab se rakam kat rahi hai |
| **Not deducted**   | Kuch nahi kat raha                    |
| **Admin adjusted** | Aapki bhari hui rakam lag rahi hai    |

Agar us avadhi me kisi ka short nahi hai to likha aata hai _No short hours in (mahina)_.

## Kaam kaise karein

### Kisi employee ki rakam badalna (Adjust)

1. **Employee Finance → Short Hours** kholein aur sahi mahina chunein.
2. Employee ki row me **Adjust** dabayein.
3. **Adjust short-hours deduction** me upar us employee ke kam ghante, rate aur hisaab ki rakam dikhti hai. Neeche bharein:

    | Field                  | Kya bharna hai                                                                              |
    | ---------------------- | ------------------------------------------------------------------------------------------- |
    | **Deduction to apply** | Jitni rakam kaatni hai                                                                      |
    | **Reason**             | Kaaran (zaroori hai, kam se kam 3 akshar). Ye payroll ke byore aur Audit Logs me dikhta hai |

4. **Save adjustment** dabayein.

Ab **Final deduction** me aapki rakam dikhegi aur label **Admin adjusted** ho jayega.

### Katauti maaf karna

**Adjust** dabakar **Deduction to apply** me **0** likhein, kaaran bharein aur **Save adjustment** dabayein. Us avadhi ke liye us employee ka kuch nahi kategi.

### Manual adjustment niyam me rakam lagana

Is niyam me koi rakam apne aap nahi katti. Jis employee se kaatna hai, uske liye **Adjust** dabakar rakam bharein. Hisaab ki rakam pehle se box me bhari hoti hai — chahein to wahi rakhein, chahein to badal dein.

### Apna badlav hatana

1. Jis row me rakam adjust ki gayi hai, wahan **Remove adjustment** dabayein.
2. **Remove adjustment** se pakka karein.

Ab us employee par phir se company ka niyam lagega.

### Company ka niyam badalna (sirf Company Admin)

1. Short Hours screen par **Change rule** dabayein, ya **Settings → Payroll** kholein.
2. **Short-hours rule** me teen me se ek niyam chunein.
3. **One short hour is worth** me rate ka tarika chunein. **A fixed rate per hour** chunne par **Fixed rate per hour** bharein.
4. **Save payroll settings** dabayein.

Poori jaankari [Company Settings guide](13-company-settings.md) me hai.

---

# Bhaag 2: Overtime

## Overtime do tarah se salary me aata hai

1. **Aapki jodi hui overtime entry** — Overtime screen par haath se jodi gayi. Isme rate ya rakam aap khud bharte hain.
2. **Attendance se bana overtime** — agar company ne **Settings → Payroll → Overtime rules** me **Pay overtime recorded in attendance** on kiya hai. Tab attendance me zaroori ghanto se zyada jo kaam dikhta hai, wo company ke rate par apne aap salary me jud jata hai.

Jis tareekh ki overtime entry aapne khud jodi hai, us tareekh ka attendance wala overtime dobara nahi ginta — yani ek hi din ka paisa do baar nahi milta.

> Nayi company me **Pay overtime recorded in attendance** off hota hai. Yani shuru me sirf aapki jodi hui entries ka paisa milta hai.

**Company ka overtime rate** (sirf attendance wale overtime ke liye):

| Vikalp                                | Matlab                                                                                                                          |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| **Hourly salary rate x a multiplier** | Employee ki ek ghante ki salary × **Multiplier**. Jaise 1.5 ka matlab dedh guna: ₹125 ka ghanta → ₹187.50 prati overtime ghanta |
| **A fixed rate per hour**             | Sabke liye ek hi tay rate                                                                                                       |

## Overtime ke char status

| Status       | Matlab                                                                 |
| ------------ | ---------------------------------------------------------------------- |
| **Pending**  | Likha gaya hai, manzoori baaki. Iska paisa nahi milta                  |
| **Approved** | Manzoor. Agle payroll me jud jayega                                    |
| **Rejected** | Namanzoor. Iska paisa nahi milta                                       |
| **Paid**     | Salary me diya ja chuka hai. Ab ise badla ya delete nahi kiya ja sakta |

**Paid** aap khud nahi chun sakte — payroll finalize hone par (ya final settlement me) ye apne aap lagta hai.

## Screen par kya dikhta hai

**Upar teen box:**

| Box                      | Matlab                                              |
| ------------------------ | --------------------------------------------------- |
| **Overtime amount**      | Us mahine ki Approved aur Paid entries ki kul rakam |
| **Overtime hours**       | Un entries ke kul ghante                            |
| **Waiting for approval** | Kitni entries Pending hain                          |

**Filter:** mahina, employee ka naam ya ID, aur **All statuses**.

| Column          | Matlab                                                 |
| --------------- | ------------------------------------------------------ |
| **Employee**    | Naam aur ID                                            |
| **Date**        | Overtime ki tareekh                                    |
| **Calculation** | Hisaab — jaise _3.00 h × ₹200.00_, ya **Fixed amount** |
| **Amount**      | Kul rakam                                              |
| **Reason**      | Kaaran                                                 |
| **Status**      | Pending / Approved / Rejected / Paid                   |

Row ke aakhir me pencil (edit) aur dustbin (delete) hote hain. Jo entry salary me di ja chuki hai, wahan inki jagah **Paid** likha hota hai.

## Kaam kaise karein

### Overtime jodna

1. **Employee Finance → Overtime** kholein.
2. **Add overtime** dabayein.
3. Form bharein:

    | Field                             | Kya bharna hai                                                              |
    | --------------------------------- | --------------------------------------------------------------------------- |
    | **Employee**                      | Employee chunein                                                            |
    | **Date**                          | Overtime ki tareekh                                                         |
    | **How is the amount worked out?** | **Hours × rate** ya **Fixed amount** (neeche dekhein)                       |
    | **Reason**                        | Kaaran, jaise "Month-end closing"                                           |
    | **Status**                        | **Approved**, **Pending** ya **Rejected**. Sirf Approved ka paisa milta hai |
    | **Notes**                         | Koi aur note                                                                |

4. Rakam ka tarika:
    - **Hours × rate** — **Hours** (kam se kam 0.25, yani 15 minute) aur **Rate per hour** bharein. Neeche turant hisaab dikhta hai, jaise _3.00 hours × ₹200.00 = ₹600.00_.
    - **Fixed amount** — **Fixed overtime amount** me seedhi rakam bharein, ghante chahe jitne hon.
5. **Add overtime** dabayein.

Status pehle se **Approved** chuna hota hai. Agar manzoori baad me leni hai to **Pending** chunein.

### Pending overtime manzoor ya namanzoor karna

Iske liye alag button nahi hai — entry ko edit karke status badalte hain:

1. Entry ki row me pencil dabayein.
2. **Status** ko **Approved** (manzoor) ya **Rejected** (namanzoor) karein.
3. **Save changes** dabayein.

Upar ke **Waiting for approval** box se pata chalta hai kitni entries baaki hain. **All statuses → Pending** chunkar unhe ek saath dekh sakte hain.

### Overtime badalna ya delete karna

- **Badalna:** pencil → badlav → **Save changes**.
- **Delete:** dustbin → **Delete overtime**.

Ye sirf tab tak ho sakta hai jab tak entry **Paid** nahi hui.

### Overtime salary me kaise pahunchta hai

1. Payroll calculate karte samay us employee ki saari **Approved** entries jo ab tak salary me nahi di gayi (payroll ki aakhri tareekh tak ki) salary me jud jaati hain.
2. Payroll ke byore me har entry **Overtime** naam ki alag line me dikhti hai, hisaab ke saath. Attendance wala overtime **Overtime (from attendance)** naam se alag dikhta hai.
3. Payroll **finalize** hone par wo entries **Paid** ho jaati hain aur lock ho jaati hain.

Agar pichhle mahine ki koi Approved entry salary me jaane se reh gayi thi, to wo agle payroll me apne aap aa jaati hai.

---

## Example

### Short hours

**Sharma Traders** ka niyam: **Deduct from salary**, rate **The employee's hourly salary rate**.

Suresh Yadav ki ek din ki salary ₹1,200 hai aur shift me 8 ghante zaroori hain → ek ghante ka rate **₹150**.
September 2026 me attendance ke hisaab se Suresh teen din jaldi gaya — kul **2h 30m** kam.

- **Calculated amount:** 2.5 × ₹150 = **₹375**
- **Final deduction:** ₹375, label **Deducted**

Suresh ne bataya ki ek din wo company ke kaam se bank gaya tha. HR ne **Adjust** dabaya, **Deduction to apply: 225**, **Reason: "10 September ko bank ke kaam se gaya tha"**.

- **Admin-adjusted amount:** ₹225
- **Final deduction:** **₹225**, label **Admin adjusted**

September ki salary me **Short Hours Deduction** ke naam se ₹225 kate, aur byore me kaaran bhi likha aaya.

### Overtime

Pooja Singh ne 30 September 2026 ko month-end closing ke liye 3 ghante zyada kaam kiya.

HR ne **Add overtime** me bhara: Employee **Pooja Singh**, Date 30-09-2026, **Hours × rate**, Hours **3**, Rate per hour **200**, Reason "Month-end closing", Status **Approved**.

- Rakam: 3 × ₹200 = **₹600**
- September ke payroll me **Overtime** ki line me ₹600 jud gaye.
- Payroll finalize hote hi entry **Paid** ho gayi.

Isi mahine Ravi ne ek Sunday ko stock ginti ke liye kaam kiya, jiske liye ₹1,000 tay the. HR ne **Fixed amount** chunkar **Fixed overtime amount: 1000** bhara.

---

## Dhyan rakhne wali baatein

**Short hours**

- **Short hours sirf tab bante hain jab attendance me aane-jaane ka time bhara ho.** Time nahi bharenge to list khali rahegi.
- **Aapki bhari hui rakam hamesha niyam se upar hoti hai** — teeno niyam me.
- **Kaaran (Reason) zaroori hai** aur payroll ke byore me dikhta hai.
- **Adjustment ek employee aur ek payroll avadhi ke liye hota hai.** Agle mahine phir se niyam lagega.
- **Record only aur Manual adjustment me bhi** kam ghante payroll ke byore me dikhte hain — bas rakam nahi katti.
- **Payroll finalize hone se pehle adjust karein.** Finalize ho chuki salary baad ke adjustment se nahi badalti. Zaroorat ho to payroll reopen karke dobara calculate karna padega — dekhein [Payroll guide](10-payroll-aur-salary-slip.md).

**Overtime**

- **Sirf Approved overtime ka paisa milta hai.** Pending aur Rejected ka nahi.
- **Paid entry lock hoti hai** — na badal sakte hain, na delete.
- **Haath se jodi entry me rate aap bharte hain.** Company ka overtime rate sirf attendance wale overtime par lagta hai.
- **Ek hi din ka overtime do baar na gina jaye**, isliye jis tareekh ki entry aapne jodi hai us tareekh ka attendance wala overtime nahi judta.
- **Overtime ki list me sirf abhi kaam kar rahe employees aate hain.**

**Dono**

- Company ka niyam ya rate badalne ka asar agli baar calculate hone wale payroll par padta hai. Finalized payroll kabhi nahi badalta.
- Har badlav **Audit Logs** me record hota hai.

**Phone par:** dono list card ke roop me dikhti hain — har employee/entry ka ek card, jisme saari rakam label ke saath likhi hoti hai. Form poori screen ki chaudai me khulte hain.

---

## Aksar pooche jane wale sawal

**Short Hours ki list khali kyun hai?**
Us avadhi me kisi ka kaam kam nahi raha, ya attendance me aane-jaane ka time bhara hi nahi gaya. Short hours time se bante hain.

**Niyam "Record only" hai, phir bhi ek employee se kaatna hai. Kaise?**
Us employee ki row me **Adjust** dabakar rakam aur kaaran bharein. Aapki rakam niyam se upar maani jaati hai.

**5–10 minute ki kami par bhi short hours ban rahe hain. Rokna hai.**
Company Admin **Settings → Attendance** me **Short-hours tolerance (minutes)** badha de (jaise 15). Isse kam ki roz ki kami nahi gini jayegi.

**Overtime approve karne ka button kahan hai?**
Alag button nahi hai. Entry ko edit karke **Status** ko **Approved** kar dein.

**Overtime entry delete nahi ho rahi.**
Wo **Paid** ho chuki hai — salary me di ja chuki hai. Paid entry badli ya hatayi nahi ja sakti.

**Attendance me overtime dikh raha hai par salary me nahi aaya.**
Company me **Pay overtime recorded in attendance** off hai. Ya to Company Admin ise **Settings → Payroll** me on kare, ya us din ke liye Overtime screen par entry jod dein.

---

Aage padhein: [Attendance](05-attendance.md) · [Salary, Bonus aur Deduction](09-salary-bonus-deduction.md) · [Payroll aur Salary Slip](10-payroll-aur-salary-slip.md) · [Company Settings](13-company-settings.md)
