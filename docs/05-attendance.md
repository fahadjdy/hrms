# Attendance (Haazri)

Ye module batata hai ki **kaun kis din aaya, kab aaya, kab gaya aur kitna kaam kiya**.
Employees khud login nahi karte — attendance HR ya Admin hi bharte hain.
Mahine ke aakhir me salary isi attendance se banti hai, isliye ise sahi rakhna bahut zaroori hai.

---

## Kahan milega

| Kaam                                                    | Menu                                                                |
| ------------------------------------------------------- | ------------------------------------------------------------------- |
| Ek din me sab employees ki attendance                   | **Attendance → Daily Attendance**                                   |
| Ek employee ka poora mahina                             | **Attendance → Attendance Calendar** → employee chunein             |
| Attendance ka tarika (Automatic / Manual), grace period | **Settings → Attendance** (ya **Attendance → Attendance Settings**) |

Calendar aur jagah se bhi khulta hai: employee ki profile par **Attendance calendar** button se, aur Daily Attendance ki list me employee ke naam par click karke.

## Kaun use kar sakta hai

| Kaam                                               | Kaun kar sakta hai (shuru me)     |
| -------------------------------------------------- | --------------------------------- |
| Attendance **dekhna**                              | Company Admin, HR Manager, Viewer |
| Attendance **mark karna, badalna, generate karna** | Company Admin, HR Manager         |
| Attendance ka tarika aur niyam badalna (settings)  | Sirf Company Admin                |

> Company Admin **Settings → Roles & Permissions** se ye adhikar badal sakta hai.

---

## Pehle ye samjhein: Automatic aur Manual attendance

Company do me se ek tarika chunti hai (**Settings → Attendance → Attendance mode**):

| Tarika        | Kaise kaam karta hai                                                                                                                                                | Kiske liye theek                                                             |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| **Automatic** | Har kaam ke din sab employees apne aap **Present** maane jaate hain. Aap sirf wo din bharte hain jo alag hain — absent, chhutti, late, kam ghante.                  | Jahan zyadatar log roz aate hain aur aap sirf gair-haazri likhna chahte hain |
| **Manual**    | Har kaam ke din ki attendance aapko khud mark karni hoti hai. Jo din mark nahi hua, wo **Not marked** dikhta hai aur salary banate samay **absent** maana jata hai. | Jahan roz ki haazri register se bharni hoti hai                              |

Dono tariko me ye teen cheezein apne aap bhar jaati hain:

- **Weekly off** (jaise Sunday)
- **Holiday** (jaise Diwali)
- **Approved leave** (manzoor chhutti)

Daily Attendance screen ke upar hamesha likha hota hai ki abhi kaun sa tarika chal raha hai.

> Nayi company me shuru me **Manual** tarika hota hai. Company Admin ise **Settings → Attendance** se badal sakta hai.

---

## Attendance status — har ek ka matlab

Calendar me har din par ek chhota code dikhta hai. Calendar ke neeche inka poora naam bhi likha hota hai.

| Code    | Status             | Matlab                                                                 | Salary par asar                                                                                                                                   |
| ------- | ------------------ | ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| **P**   | **Present**        | Poora din kaam kiya                                                    | Poori salary                                                                                                                                      |
| **A**   | **Absent**         | Bina chhutti ke nahi aaya                                              | Ek din ki salary katti hai                                                                                                                        |
| **HD**  | **Half Day**       | Aadha din kaam kiya                                                    | Aadhe din ki salary katti hai                                                                                                                     |
| **PL**  | **Paid Leave**     | Salary wali chhutti                                                    | Kuch nahi katta                                                                                                                                   |
| **UL**  | **Unpaid Leave**   | Bina salary ki chhutti                                                 | Ek din ki salary katti hai                                                                                                                        |
| **H**   | **Holiday**        | Company/sarkari chhutti                                                | Kuch nahi katta, absent nahi ginti                                                                                                                |
| **OFF** | **Weekly Off**     | Hafte ki chhutti                                                       | Kuch nahi katta, absent nahi ginti                                                                                                                |
| **WFH** | **Work From Home** | Ghar se kaam kiya                                                      | Poori salary (Present ki tarah ginti hai)                                                                                                         |
| **L**   | **Late**           | Aaya, lekin der se                                                     | Present ginti hai. Company ke niyam ke hisaab se kuch late par aadha din kat sakta hai (neeche dekhein)                                           |
| **SH**  | **Short Hours**    | Aaya, lekin zaroori ghanto se kam kaam kiya                            | Present ginti hai. Kam ghanto ka paisa company ke short-hours niyam se tay hota hai — dekhein [Short Hours guide](07-short-hours-aur-overtime.md) |
| **O**   | **Other**          | Koi khaas din jo upar me se kisi me nahi aata                          | Kuch nahi katta                                                                                                                                   |
| **?**   | **Not marked**     | Kaam ka din hai par attendance bhari nahi gayi (sirf Manual tarike me) | Salary banate samay absent maana jata hai                                                                                                         |

Iske alawa do aur shabd dikh sakte hain:

- **Upcoming** — aane wali tareekh. Abhi mark nahi kar sakte.
- **Not employed** — employee ke joining se pehle ya last working day ke baad ka din. Ise mark nahi kar sakte.

**Present, Late aur Short Hours ek hi tarah ke din hain.** Agar aap **Present** chunkar check-in aur check-out ka time bharte hain, to system khud dekh leta hai:

- der se aaya → status **Late** ho jata hai,
- kam ghante kaam kiya → status **Short Hours** ho jata hai,
- sab theek → **Present** hi rehta hai.

---

# Bhaag 1: Daily Attendance

## Screen par kya dikhta hai

**Daily Attendance** ek tareekh ke liye sab employees ki attendance dikhata hai.

**Upar:**

- Tareekh, din aur attendance ka tarika likha hota hai.
- **Generate attendance** button.
- Tareekh badalne ke liye: pichhla din (teer), tareekh ka box, agla din (teer), aur **Go to today**.
- Agar wo din holiday, weekly off ya aane wali tareekh hai to ek neeli patti me bataya jata hai.

**Ginti ke box (us din ka saar):**

| Box                 | Matlab                                            |
| ------------------- | ------------------------------------------------- |
| **Total employees** | Us din company me kitne log the                   |
| **Present**         | Kitne aaye (Late, WFH aur Half Day bhi isme hain) |
| **Absent**          | Kitne absent mark hue                             |
| **On leave**        | Kitne chhutti par (paid aur unpaid dono)          |
| **WFH**             | Kitne ghar se kaam kar rahe hain                  |
| **Late**            | Kitne der se aaye                                 |
| **Short hours**     | Kitno ne kam ghante kaam kiya                     |
| **Overtime**        | Kitno ne zyada kaam kiya                          |
| **Not marked**      | Kitno ki attendance abhi bhari nahi gayi          |

**Absent**, **WFH**, **Late** aur **Not marked** box par click karne se list sirf unhi logon ki ho jaati hai.

**Filter:** naam/ID/email se khoj, **All departments**, **All statuses**.

**List ke column:**

| Column               | Matlab                                                          |
| -------------------- | --------------------------------------------------------------- |
| **Select**           | Kai logon ko ek saath mark karne ke liye tick                   |
| **Employee**         | Naam, ID, designation. Naam par click → us employee ka calendar |
| **Department**       | Vibhag                                                          |
| **Status**           | Us din ka status                                                |
| **In / out**         | Aane aur jaane ka time                                          |
| **Worked**           | Kitna kaam kiya                                                 |
| **Short**            | Kitna kam raha                                                  |
| **Overtime**         | Kitna zyada kiya                                                |
| (aakhir me) **Mark** | Us employee ki attendance bharne ka button                      |

## Kaam kaise karein

### Ek employee ki attendance mark karna

1. **Attendance → Daily Attendance** kholein aur sahi tareekh chunein.
2. Employee ki row me **Mark** dabayein.
3. **Mark attendance** me bharein:

    | Field         | Kya bharna hai                                        |
    | ------------- | ----------------------------------------------------- |
    | **Status**    | Present, Absent, Half Day, Work From Home… jo sahi ho |
    | **Check in**  | Aane ka time (chahein to)                             |
    | **Check out** | Jaane ka time (chahein to)                            |
    | **Notes**     | Koi chhota note                                       |

4. **Save attendance** dabayein.

**Time bharna ya nahi?**

- **Time khali chhodein** → poora din gina jata hai (poore zaroori ghante).
- **Dono time bharein** → system employee ki shift se khud hisaab lagata hai: kitna kaam hua, kitna kam raha, kitna overtime, kitni der se aaya.
- **Sirf ek time mat bharein** — ya to dono bharein ya dono khali chhodein.

### Kai employees ko ek saath mark karna

1. List me jin logon ko mark karna hai unke saamne tick lagayein. Sabko chunna ho to **Select everyone on this page** par tick karein.
2. Upar ek patti aati hai jisme likha hota hai kitne log chune gaye.
3. **Mark selected as** me status chunein. Chahein to **Check in** aur **Check out** bhi bharein.
4. **Mark … employees** dabayein.

Chunav hatana ho to **Clear selection** dabayein.

> Tip (Manual tarike me): pehle sabko chunkar **Present** mark kar dein, phir jo absent ya chhutti par hain sirf unhe ek-ek karke badal dein.

### Attendance generate karna

**Generate attendance** un dino ko pakka (save) kar deta hai jinke liye aapko kuch tay nahi karna:

- **Automatic** tarike me: weekly off, holiday aur Present ke din.
- **Manual** tarike me: sirf weekly off aur holiday. Kaam ke din tab tak khali rehte hain jab tak aap mark na karein.

1. **Generate attendance** dabayein.
2. **From** aur **To** tareekh chunein.
3. **Generate attendance** dabayein.

Jin dino ki attendance pehle se bhari hai, unhe ye **kabhi nahi badalta**. Aane wali tareekhon ke liye generate nahi hota. Lambi avadhi chunne par kaam peeche chalta hai aur thodi der me poora ho jata hai.

### Bhara hua record hatana

Galti se mark ho gaya? **Mark** dabakar **Clear record** dabayein. Wo din phir se wahi status dikhayega jo calendar aur attendance ke tarike se banta hai (jaise Automatic me Present, ya Holiday).

---

# Bhaag 2: Attendance Calendar (ek employee ka poora mahina)

## Screen par kya dikhta hai

**Attendance → Attendance Calendar** kholne par employees ke card dikhte hain. Naam/ID/email se khoj sakte hain ya department chun sakte hain. Kisi employee par click karein — uska mahine ka calendar khulta hai.

**Employee ke calendar par:**

- Upar employee ka naam, aur uski shift — jaise _General Shift: 9:00 AM to 6:00 PM, 8h 00m required (company default)_.
- **Mahina badalne** ke teer.
- **Calendar** aur **List** ke do button — dekhne ka tarika badalne ke liye.
- Mahine ka calendar ya list.
- Status codes ki soochi (P, A, HD…).
- **Summary for (mahina)** — mahine ka saar.
- Neeche **Choose another employee** — doosra employee chunne ke liye.

**Calendar view:** saat column ka mahina. Har din ke khane me tareekh aur status ka code hota hai. Badi screen par aane-jaane ka time, kaam ke ghante, kam ghante (_Short_) aur overtime (_OT_) bhi dikhte hain. Aaj ki tareekh gol ghere me hoti hai. Bina mark wale din par **?** aata hai.

**List view:** har din ek line me — tareekh, din, status, aane-jaane ka time, kaam ke ghante, Short aur OT.

> **Phone par** calendar ke khane bahut chhote ho jaate hain, isliye phone par apne aap **List** khulti hai. Aap **Calendar** dabakar badal sakte hain — aapki pasand yaad rakhi jaati hai.

## Kaam kaise karein

### Kisi din ka poora byora dekhna

Calendar ya list me kisi bhi din par click karein. Daayi taraf se ek panel khulta hai (phone par poori screen par):

| Jaankari                 | Matlab                                                                                                  |
| ------------------------ | ------------------------------------------------------------------------------------------------------- |
| Upar ki line             | Batati hai ye record kaise bana — _Marked by an admin_, _Recorded automatically_, ya abhi save nahi hua |
| **Status**               | Us din ka status (holiday ho to uska naam bhi)                                                          |
| **Check in / Check out** | Aane aur jaane ka time                                                                                  |
| **Required hours**       | Us din kitna kaam zaroori tha                                                                           |
| **Worked**               | Kitna kaam hua                                                                                          |
| **Short hours**          | Kitna kam raha                                                                                          |
| **Overtime**             | Kitna zyada hua                                                                                         |
| **Late by**              | Kitni der se aaya (sirf late hone par dikhta hai)                                                       |
| **Notes**                | Note, agar likha gaya ho                                                                                |

### Kisi din ko mark karna ya sudharna

1. Us din par click karein.
2. Panel me **Mark this day** (ya pehle se bhare din par **Edit this day**) ke neeche:

    | Field                     | Kya bharna hai                                                                           |
    | ------------------------- | ---------------------------------------------------------------------------------------- |
    | **Status**                | Sahi status                                                                              |
    | **Check in / Check out**  | Time, agar bharna ho. Absent aur leave ke liye ye box nahi aate                          |
    | **Notes**                 | Note                                                                                     |
    | **Reason for the change** | Badlav ka kaaran, jaise "Register me galti thi". Ye history me hamesha ke liye rehta hai |

3. **Save attendance** dabayein.

Mahine ka summary turant badal jata hai.

### Kisi din ka badlav-itihaas dekhna

Us din ke panel me sabse neeche **Change history** hoti hai. Isme har badlav likha hota hai:

- kya badla (jaise _Present changed to Absent_),
- kaaran,
- kisne badla aur kab.

Ye itihaas mitaya ya badla nahi ja sakta.

### Bhara hua record hatana

Panel me **Clear record** dabayein. Din wapas apne asli status par chala jata hai. Ye bhi history me likha jata hai.

## Mahine ka summary

Calendar ke neeche **Summary for (mahina)** me ye sab hota hai:

| Ginti                                | Matlab                                                                                                            |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------------------- |
| **Working days**                     | Mahine me kaam ke din (weekly off aur holidays hata kar)                                                          |
| **Present**                          | Aaya hua din (Late, Short Hours aur WFH bhi isme hain)                                                            |
| **Absent**                           | Absent din                                                                                                        |
| **Half day**                         | Aadhe din                                                                                                         |
| **Paid leave** / **Unpaid leave**    | Chhutti ke din                                                                                                    |
| **Weekly off** / **Public holidays** | Chhutti ke din                                                                                                    |
| **WFH**                              | Ghar se kaam ke din                                                                                               |
| **Late**                             | Der se aane ke din                                                                                                |
| **Not marked**                       | Bina mark wale din (sirf tab dikhta hai jab aise din hon)                                                         |
| **Required hours**                   | Mahine me kitna kaam zaroori tha                                                                                  |
| **Worked hours**                     | Kitna kaam hua                                                                                                    |
| **Short hours**                      | Kul kitna kam raha                                                                                                |
| **Overtime**                         | Kul kitna zyada hua                                                                                               |
| **Attendance rate**                  | Ab tak beete kaam ke dino me se kitne pratishat din aaya. Paid leave ko aaya hua gina jata hai, half day ko aadha |

---

## Late, kam ghante aur overtime ka hisaab kaise hota hai

Ye hisaab tabhi banta hai jab aap **check-in aur check-out dono** bharte hain.

- **Late:** agar check-in, shift shuru hone ke time se **grace period** se zyada der ka hai. Grace period **Settings → Attendance → Grace period (minutes)** me hota hai.
- **Worked:** aane se jaane tak ka time, usme se shift ka break ghata kar. (Agar employee aadhi shift se kam ruka, to break nahi ghataya jata.)
- **Short hours:** zaroori ghanto se jitna kam kaam hua. Agar kami **Short-hours tolerance (minutes)** jitni ya usse kam hai, to use kam nahi gina jata.
- **Overtime:** zaroori ghanto se jitna zyada kaam hua.
- **Chhutti ke din kaam:** agar Holiday, Weekly Off ya Other wale din time bhara jaye, to poora kaam ka time overtime gina jata hai.

**Late par salary katna:** **Settings → Attendance** me **Late arrivals per half-day deduction** hota hai. Jaise 3 likha hai to mahine me har 3 late par aadhe din ki salary katti hai. 0 likha ho to late par kuch nahi katta.

---

## Example

**Company ka tarika: Automatic. Shift: 9:00 AM to 6:00 PM, 8 ghante zaroori, 1 ghanta break. Grace period: 10 minute. Short-hours tolerance: 0 minute.**

Amit Verma ka September 2026:

- Zyadatar din kuch nahi karna pada — calendar me apne aap **P** dikh raha hai.
- **7 September** ko Amit bina bataye nahi aaya. HR ne us din par click kiya, **Status: Absent**, **Reason for the change: "Bina soochna ke absent"**, **Save attendance**. Calendar me **A** aa gaya.
- **15 September** ko Amit 9:40 AM aaya aur 6:00 PM gaya. HR ne **Present** chunkar dono time bhare. System ne khud status **Late** kar diya (40 minute der, jo 10 minute ke grace period se zyada hai). Kaam: 8h 20m ruka − 1h break = 7h 20m, yani **40 minute short**.
- **22 September** ko Amit ne 9:00 AM se 8:00 PM tak kaam kiya. Time bharne par **2h 00m overtime** dikha.

Mahine ke summary me: Absent 1, Late 1, Short hours 0h 40m, Overtime 2h 00m.
Salary banate samay 1 din ki salary absent ke liye kategi; short hours aur overtime ka paisa company ke niyam ke hisaab se lagega.

---

## Dhyan rakhne wali baatein

- **Aane wali tareekh ki attendance nahi bhar sakte.**
- **Joining se pehle ya last working day ke baad** ke din mark nahi hote.
- **Har badlav record hota hai** — kisne, kab, kya badla aur kyun. Ye day panel ki **Change history** aur **Audit Logs** dono me dikhta hai. Isliye **Reason for the change** hamesha bharein.
- **Manual tarike me bina mark wala din absent maana jata hai.** Salary banane se pehle **Not marked** box me 0 hona chahiye. Daily Attendance me **All statuses → Not marked** chunkar aise log turant mil jaate hain.
- **Approved leave apne aap attendance me aa jaati hai.** Use yahan haath se mark karne ki zaroorat nahi — chhutti [Leave](06-leave.md) se jodein.
- **Holiday aur weekly off calendar se aate hain.** Unhe badalna ho to [Work Shifts aur Holidays](04-work-shifts-aur-holidays.md) dekhein.
- **Finalized payroll apne aap nahi badalta.** Agar salary finalize hone ke baad us mahine ki attendance badli, to finalized salary wahi rehti hai. Salary sahi karni ho to payroll ko reopen karke dobara calculate karna padta hai — dekhein [Payroll guide](10-payroll-aur-salary-slip.md).
- **Short hours aur overtime sirf time bharne par bante hain.** Bina check-in/check-out ke din poora gina jata hai.

**Phone par:** Daily Attendance ki list card ke roop me dikhti hai (har employee ka ek card, Department column nahi dikhta). Calendar ki jagah List khulti hai. Day panel poori screen par khulta hai.

---

## Aksar pooche jane wale sawal

**Automatic tarike me bhi kya mujhe roz kuch karna padega?**
Nahi. Sirf un dino ko badlein jo alag hain — absent, half day, late, WFH. Baaki din apne aap Present gine jaate hain.

**Calendar me din par "?" kyun aa raha hai?**
Company ka tarika Manual hai aur us kaam ke din ki attendance bhari nahi gayi. Us din par click karke mark kar dein.

**Maine Present chuna tha, status Late kaise ho gaya?**
Aapne check-in ka time bhara jo shift ke time aur grace period se zyada der ka tha. System time dekhkar Present ko Late ya Short Hours me badal deta hai.

**Galat din mark ho gaya. Kya delete kar sakte hain?**
Haan. Us din ko kholkar **Clear record** dabayein. Din apne asli status par wapas chala jata hai. Ye badlav history me likha rehta hai.

**Employee chhutti ke din (Sunday) kaam par aaya. Kaise likhein?**
Us din ko kholein, status **Weekly Off** hi rehne dein aur check-in/check-out bhar dein. Poora time overtime me gina jayega.

**Kya employee apni attendance khud laga sakta hai?**
Nahi. Is system me employee login nahi karte. Attendance sirf HR/Admin bharte hain.

---

Aage padhein: [Leave](06-leave.md) · [Short Hours aur Overtime](07-short-hours-aur-overtime.md) · [Payroll aur Salary Slip](10-payroll-aur-salary-slip.md) · [Reports aur Audit Log](12-reports-aur-audit-log.md)
