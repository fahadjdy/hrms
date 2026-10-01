# Leave (Chhutti)

Ye module employees ki **chhutti ka hisaab** rakhta hai — kisne kab chhutti li, kaun si chhutti li, aur kitni baaki hai.
Employees khud login nahi karte, isliye chhutti HR ya Admin jodte hain aur wahi manzoor (approve) karte hain.
Manzoor chhutti apne aap attendance me aa jaati hai, aur bina salary wali chhutti salary me se kat jaati hai.

Is guide me teen cheezein hain:

1. **Leave Types** — chhutti ke prakar (Casual, Sick, Unpaid…).
2. **Leave Records** — chhutti jodna, manzoor karna, radd karna.
3. **Leave Balance** — kis employee ki kitni chhutti baaki hai.

---

## Kahan milega

| Kaam                                           | Menu                                                                         |
| ---------------------------------------------- | ---------------------------------------------------------------------------- |
| Chhutti jodna, approve / reject / cancel karna | **Leave → Leave Records**                                                    |
| Chhutti ke prakar banana                       | **Leave → Leave Types** (yahi screen **Settings → Leave** se bhi khulti hai) |
| Baaki chhutti dekhna / badalna                 | **Leave → Leave Balance**                                                    |

## Kaun use kar sakta hai

| Kaam                                                        | Kaun kar sakta hai (shuru me)     |
| ----------------------------------------------------------- | --------------------------------- |
| Chhutti, prakar aur balance **dekhna**                      | Company Admin, HR Manager, Viewer |
| Chhutti **jodna, badalna, approve / reject / cancel karna** | Company Admin, HR Manager         |
| Leave type **banana / badalna / delete karna**              | Company Admin, HR Manager         |
| Leave balance **badalna**                                   | Company Admin, HR Manager         |

> Company Admin **Settings → Roles & Permissions** se ye adhikar badal sakta hai.

---

## Pehle ye samjhein

**Paid aur Unpaid chhutti**

| Prakar     | Salary par asar                                             |
| ---------- | ----------------------------------------------------------- |
| **Paid**   | Salary nahi katti. Chhutti balance me se ghatti hai         |
| **Unpaid** | Har din ki salary katti hai (ek din ki salary ke hisaab se) |

**Chhutti ke char status**

| Status        | Matlab                                                                         |
| ------------- | ------------------------------------------------------------------------------ |
| **Pending**   | Chhutti likhi gayi hai, faisla baaki hai. Attendance me abhi nahi aayi         |
| **Approved**  | Manzoor. Attendance me likh di gayi hai aur balance me gini ja rahi hai        |
| **Rejected**  | Namanzoor. Attendance me kuch nahi likha gaya. Ise baad me badla nahi ja sakta |
| **Cancelled** | Radd. Attendance se chhutti ke din hata diye gaye, balance me ab nahi ginti    |

**Chhutti ke din kaise gine jaate hain**

Sirf **kaam ke din** gine jaate hain. Chhutti ki tareekhon ke beech aane wale weekly off aur holidays nahi gine jaate.
Jaise Friday se Monday ki chhutti, aur Sunday weekly off hai → 3 din (Friday, Saturday, Monday).
**Half day** hamesha 0.5 din ginta hai.

---

# Bhaag 1: Leave Types

## Screen par kya dikhta hai

| Column             | Matlab                                               |
| ------------------ | ---------------------------------------------------- |
| **Leave type**     | Chhutti ka naam                                      |
| **Code**           | Chhota naam, jaise CL                                |
| **Pay**            | **Paid** ya **Unpaid**                               |
| **Days per year**  | Saal me kitne din milte hain                         |
| **Leave recorded** | Is prakar ki kitni chhuttiyan ab tak likhi gayi hain |
| **Status**         | **Active** ya **Inactive**                           |

> Nayi company me ye char prakar pehle se bane hote hain: **Casual Leave (CL)** 12 din, **Sick Leave (SL)** 12 din, **Paid Leave (PL)** 15 din, aur **Unpaid Leave (UL)**. Aap inhe badal sakte hain ya naye jod sakte hain.

## Kaam kaise karein

### Naya leave type banana

1. **Leave → Leave Types** kholein.
2. **Add leave type** dabayein.
3. Form bharein:

    | Field             | Kya bharna hai                                                                                                          |
    | ----------------- | ----------------------------------------------------------------------------------------------------------------------- |
    | **Name**          | Jaise "Casual Leave"                                                                                                    |
    | **Code**          | Chhota naam, jaise "CL". Sirf akshar aur ank, bina space ke. Ek code do baar nahi ho sakta                              |
    | **Days per year** | Saal me kitne din. Aadha din bhi chalega (jaise 7.5). Jis chhutti ki koi seema nahi (jaise unpaid), wahan **0** likhein |
    | **Paid leave**    | On → salary nahi kategi. Off → har din ki salary kategi                                                                 |
    | **Active**        | On rakhein agar ye prakar use hona hai                                                                                  |

4. **Add leave type** dabayein.

**Days per year** har employee ka shuruaati balance hota hai. Kisi ek employee ke liye ise **Leave Balance** me alag se badla ja sakta hai.

### Leave type badalna, band karna ya delete karna

- **Badalna:** pencil ka nishan → badlav → **Save changes**.
- **Band karna:** edit karke **Active** off karein. Inactive prakar nayi chhutti jodte samay list me nahi aata aur Leave Balance me bhi nahi dikhta.
- **Delete karna:** dustbin ka nishan → **Delete leave type**. Jis prakar me ek bhi chhutti likhi ja chuki hai, wo delete nahi hota — use Inactive kar dein.

---

# Bhaag 2: Leave Records

## Screen par kya dikhta hai

- Upar **Add leave** button.
- Agar kuch chhuttiyan faisle ke intezaar me hain to peeli patti aati hai, jaise _3 leave requests are waiting for a decision._ Uske saath **Show pending leave** — dabane par sirf pending chhuttiyan dikhti hain.
- **Filter:** employee ka naam ya ID, **All statuses**, **All leave types**, aur do tareekh (kis tareekh se, kis tareekh tak).

| Column         | Matlab                                                    |
| -------------- | --------------------------------------------------------- |
| **Employee**   | Naam aur ID. Click karne par profile khulti hai           |
| **Leave type** | Prakar, neeche **Paid** ya **Unpaid**                     |
| **Dates**      | Kab se kab tak. Aadhe din par **Half day** likha hota hai |
| **Days**       | Kitne din gine gaye                                       |
| **Reason**     | Kaaran (phone par ye column nahi dikhta)                  |
| **Status**     | Pending / Approved / Rejected / Cancelled                 |

Row ke aakhir me button hote hain (status ke hisaab se):

| Status               | Kaun se button                                                   |
| -------------------- | ---------------------------------------------------------------- |
| Pending              | **Approve**, **Reject**, pencil (edit), gol-kata nishan (cancel) |
| Approved             | pencil (edit), gol-kata nishan (cancel)                          |
| Rejected / Cancelled | Koi button nahi                                                  |

## Kaam kaise karein

### Chhutti jodna

1. **Leave → Leave Records** kholein.
2. **Add leave** dabayein.
3. Form bharein:

    | Field                        | Kya bharna hai                                                  |
    | ---------------------------- | --------------------------------------------------------------- |
    | **Employee**                 | Employee chunein                                                |
    | **Leave type**               | Prakar chunein. Naam ke saath (paid) ya (unpaid) likha hota hai |
    | **Half day**                 | Aadhe din ki chhutti ho to on karein                            |
    | **First day** / **Last day** | Chhutti kab se kab tak. Half day par sirf ek **Date** aati hai  |
    | **Reason**                   | Kaaran (chahein to)                                             |
    | **Notes**                    | Andar ka note, sirf HR ke liye (chahein to)                     |
    | **Save as**                  | Neeche dekhein                                                  |

4. **Save as** me se ek chunein:
    - **Approved - write it to attendance now** — chhutti turant manzoor hokar attendance me likh di jaati hai. (Ye pehle se chuna hota hai.)
    - **Pending - decide later** — chhutti sirf likh li jaati hai, faisla baad me.
5. **Add leave** dabayein.

### Pending chhutti manzoor (approve) karna

1. Chhutti ki row me **Approve** dabayein.
2. **Approve leave** se pakka karein.

Chhutti ke har kaam ke din par attendance me **Paid Leave** ya **Unpaid Leave** likh diya jata hai, aur balance me se din ghat jaate hain.

### Pending chhutti namanzoor (reject) karna

1. **Reject** dabayein.
2. **Reject leave** se pakka karein.

Attendance me kuch nahi likha jata. **Rejected chhutti ko baad me badla nahi ja sakta** — zaroorat ho to nayi chhutti jodein.

### Chhutti badalna (edit)

1. Pending ya Approved chhutti ki row me pencil dabayein.
2. Prakar, tareekh, half day, reason ya notes badlein. (Employee nahi badla ja sakta.)
3. **Save changes** dabayein.

Agar chhutti Approved thi, to attendance bhi nayi tareekhon ke hisaab se apne aap theek ho jaati hai.

### Chhutti radd (cancel) karna

1. Pending ya Approved chhutti ki row me gol-kata nishan dabayein.
2. **Cancel leave** se pakka karein.

Chhutti ke din attendance se hat jaate hain aur balance me wapas jud jaate hain.

---

# Bhaag 3: Leave Balance

## Screen par kya dikhta hai

- Upar **saal** aur dono taraf teer — pichhla ya agla saal dekhne ke liye.
- Naam/ID/email se khoj, aur **All departments**.
- Har employee ka ek card. Usme har active leave type ka ek chhota box:

| Box me          | Matlab                                                                           |
| --------------- | -------------------------------------------------------------------------------- |
| **… days left** | Kitne din baaki. Agar zyada chhutti le li to ye minus me laal rang me dikhta hai |
| **Allocated**   | Saal ke liye diye gaye din                                                       |
| **Adjustment**  | Haath se joda ya ghataya gaya (sirf tab dikhta hai jab 0 na ho)                  |
| **Used**        | Approved chhutti ke din                                                          |
| **Pending**     | Faisle ke intezaar wali chhutti ke din (sirf tab dikhta hai jab hon)             |

**Hisaab:** Baaki = Allocated + Adjustment − Used

**Used** aur **Pending** hamesha Leave Records se gine jaate hain — inhe haath se nahi badla ja sakta.

## Kaam kaise karein

### Kisi employee ka balance badalna

1. **Leave → Leave Balance** kholein aur sahi saal chunein.
2. Employee ke card me us leave type ke box par pencil dabayein.
3. Bharein:

    | Field              | Kya bharna hai                                      |
    | ------------------ | --------------------------------------------------- |
    | **Allocated days** | Is saal ke liye kul din                             |
    | **Adjustment**     | Din jodne ke liye jaise 2, ghatane ke liye jaise -1 |

4. Neeche ek line turant hisaab dikhati hai, jaise _12 allocated + 2 adjustment - 5 used = 9 left_.
5. **Save balance** dabayein.

**Kab kaam aata hai:**

- Beech saal me join karne wale ko kam chhutti deni ho → **Allocated days** kam karein.
- Pichhle saal ki bachi chhutti aage le jaani ho → **Adjustment** me jod dein.
- Kisi ko inaam me extra chhutti deni ho → **Adjustment** me jod dein.

---

## Chhutti ka attendance aur salary par asar

| Chhutti           | Attendance me kya dikhta hai              | Salary par asar                                   |
| ----------------- | ----------------------------------------- | ------------------------------------------------- |
| Paid, poora din   | **Paid Leave (PL)**                       | Kuch nahi katta                                   |
| Unpaid, poora din | **Unpaid Leave (UL)**                     | Ek din ki salary katti hai                        |
| Paid, half day    | **Paid Leave (PL)**, note me "(half day)" | Kuch nahi katta; balance me se 0.5 din ghatta hai |
| Unpaid, half day  | **Half Day (HD)**                         | Aadhe din ki salary katti hai                     |

- Chhutti sirf **kaam ke dino** par likhi jaati hai. Beech ke weekly off aur holiday waise hi rehte hain.
- Agar us din pehle se kuch aur attendance bhari thi (jaise Present), to manzoor chhutti use badal deti hai.
- Salary slip aur payroll me bina salary wali chhutti **Unpaid Leave** ke naam se alag line me dikhti hai.

---

## Example

**Neha Gupta** ko 12 se 14 October 2026 (Monday–Wednesday) ki chhutti chahiye. Uske paas Casual Leave ke 12 me se 4 din use ho chuke hain.

1. HR ne **Add leave** dabaya: Employee **Neha Gupta**, Leave type **Casual Leave (paid)**, First day 12-10-2026, Last day 14-10-2026, Reason "Ghar me shaadi", Save as **Approved - write it to attendance now**.
2. Chhutti 3 din ki bani aur Approved ho gayi.
3. Neha ke attendance calendar me 12, 13, 14 October par **PL** aa gaya.
4. Leave Balance me Casual Leave: Allocated 12, Used 7, **5 days left**.
5. October ki salary me kuch nahi kata, kyunki chhutti paid hai.

Baad me Neha ne 16 October ko ek din aur chhutti maangi, par uski saari paid chhutti bachani thi. HR ne **Unpaid Leave** chunkar joda.
Agar Neha ki ek din ki salary ₹1,000 hai, to October ki salary me **Unpaid Leave** ke naam se ₹1,000 kat gaye.

---

## Dhyan rakhne wali baatein

- **Ek employee ki do chhuttiyan ek hi tareekhon par nahi ho sakti.** Aisa karne par likha aata hai: _This employee already has leave in the selected dates._ Pehle wali chhutti edit ya cancel karein.
- **Balance se zyada chhutti par system rokta nahi hai.** Balance minus me (laal rang me) dikhta hai. Chhutti dene se pehle Leave Balance dekh lein. Agar balance khatam hai to **Unpaid Leave** chunein.
- **Rejected aur Cancelled chhutti badli nahi ja sakti.** Nayi chhutti jodein.
- **Manzoor chhutti ko attendance me haath se mat badlein.** Agar employee chhutti ke din aa gaya, to chhutti ko **edit** ya **cancel** karein. Sirf attendance badalne se chhutti Approved hi rehti hai aur balance me ginti rehti hai.
- **Chhutti cancel karne ke baad attendance dekh lein.** Manual attendance wali company me wo din **Not marked** ho jaate hain — unhe mark karna hoga. Dekhein [Attendance guide](05-attendance.md).
- **Chhutti kis saal ke balance me ginegi**, ye chhutti ke pehle din ki tareekh se tay hota hai.
- **Finalized payroll apne aap nahi badalta.** Salary finalize hone ke baad us mahine ki chhutti jodne ya radd karne se finalized salary nahi badalti.
- **Har kaam record hota hai** — chhutti jodna, manzoor karna, radd karna aur balance badalna, sab **Audit Logs** me dikhta hai.

**Phone par:** Leave Records aur Leave Types ki list card ke roop me dikhti hai. Leave Balance me har employee ke box ek ke neeche ek aa jaate hain.

---

## Aksar pooche jane wale sawal

**Employee ne chhutti ke liye phone par bola hai, abhi pakka nahi. Kaise likhein?**
**Add leave** me **Save as → Pending - decide later** chunein. Pakka hone par **Approve** ya **Reject** dabayein.

**Aadhe din ki chhutti kaise dein?**
**Add leave** me **Half day** on karein aur tareekh chunein. Ye 0.5 din ginta hai.

**Galti se chhutti approve ho gayi. Ab?**
Us chhutti ko **cancel** kar dein. Attendance se din hat jayenge aur balance wapas aa jayega.

**Naye saal me balance kaise shuru hota hai?**
Har saal ka balance us leave type ke **Days per year** se shuru hota hai. Pichhle saal ki bachi chhutti apne aap aage nahi jaati — le jaani ho to naye saal me **Adjustment** me jod dein.

**Leave type delete nahi ho raha.**
Us prakar me chhutti likhi ja chuki hai. Use edit karke **Active** off kar dein.

**Kya employee khud chhutti ke liye apply kar sakta hai?**
Nahi. Is system me employee login nahi karte. Chhutti HR/Admin hi jodte hain.

---

Aage padhein: [Attendance](05-attendance.md) · [Work Shifts aur Holidays](04-work-shifts-aur-holidays.md) · [Payroll aur Salary Slip](10-payroll-aur-salary-slip.md) · [Employees](03-employees.md)
