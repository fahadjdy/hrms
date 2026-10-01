# Payroll aur Salary Slip

Payroll wo jagah hai jahan har mahine sabhi employees ki salary banti hai. System salary, attendance, leave, short hours, overtime, bonus, deduction aur borrow, sab ko jod-ghata kar har employee ki rakam nikaalta hai.
Aap use check karte hain, zaroorat ho to reason ke saath badalte hain, aur aakhir me finalize karke lock kar dete hain. Finalize ke baad har employee ki salary slip (PDF) ban jaati hai.

## Kahan milega

| Kaam                                                  | Menu                          |
| ----------------------------------------------------- | ----------------------------- |
| Mahine ka payroll banana, check karna, finalize karna | **Payroll → Payroll**         |
| Salary slip dekhna / download karna                   | **Payroll → Salary Slips**    |
| Mahine-dar-mahine aur department ke hisaab se total   | **Payroll → Payroll Reports** |

## Kaun use kar sakta hai

| Role              | Payroll dekhna | Start / Calculate / Adjust / Delete | Finalize / Reopen | Salary slip                     |
| ----------------- | -------------- | ----------------------------------- | ----------------- | ------------------------------- |
| **Company Admin** | Haan           | Haan                                | Haan              | Haan                            |
| **HR Manager**    | Haan           | Haan                                | Haan              | Haan                            |
| **Viewer**        | Haan           | Nahi                                | Nahi              | Dekh aur download kar sakta hai |

Apne banaye role ke liye **Settings → Roles & Permissions** me teen permission hain: **View payroll and salary**, **Run payroll and revise salary**, aur **Finalize and reopen payroll**. Aap chahein to finalize ka haq sirf kuch logon ko de sakte hain.

Employees ka koi login nahi hota. Salary slip sirf admin / HR user kholte hain aur employee ko dete hain.

## Payroll ke 5 step (Status)

Har mahine ka ek hi payroll hota hai aur wo in step se guzarta hai. Payroll page par ye step upar number ke saath dikhte hain.

| Status           | Seedhi bhasha me                                                                                  |
| ---------------- | ------------------------------------------------------------------------------------------------- |
| **Draft**        | Mahine ka payroll khol diya gaya hai par abhi hisaab nahi laga. Koi rakam nahi hai                |
| **Calculated**   | System ne har employee ki salary nikaal di hai. Kisi ne haath se kuch nahi badla                  |
| **Admin Review** | Aapne bata diya ki ab check karna shuru hai. Ye step sirf nishani ke liye hai, rakam nahi badalti |
| **Adjusted**     | Kam se kam ek employee ki rakam haath se badli gayi hai (adjustment)                              |
| **Finalized**    | Payroll pakka aur lock. Ab kuch nahi badal sakta jab tak use reopen na karein                     |

**Admin Review** zaroori step nahi hai. Aap **Calculated** ya **Adjusted** se seedha finalize bhi kar sakte hain. **Draft** se finalize nahi hota.

## Screen par kya dikhta hai

### Payroll list

Har mahine ki ek row: **Month** (aur uske neeche period ki tareekh), **Status**, **Employees**, **Gross salary**, **Deductions**, **Borrow given**, **Borrow recovery**, **Net payable**. Finalize hue payroll ke status par taala bana hota hai. Row me **Open** button se payroll khulta hai.

### Ek mahine ka payroll page

- Upar step ki line aur button: **Calculate payroll** / **Recalculate**, **Start admin review**, **Finalize payroll**, **Reopen payroll**, **Salary slips**, **Export CSV**, **Delete**. Jo button us waqt kaam ka nahi hota wo dikhta nahi.
- 6 card:

| Card                 | Matlab                                                         |
| -------------------- | -------------------------------------------------------------- |
| **Employees**        | Is payroll me kitne employee hain                              |
| **Gross salary**     | Overtime aur bonus se pehle ki kul salary                      |
| **Total deductions** | Attendance, short hours, borrow recovery aur baaki sab katauti |
| **Borrow recovered** | Katauti me se borrow ki kisht ka hissa                         |
| **Borrow given**     | Salary ke saath diya gaya advance. Ye kamaai nahi hai          |
| **Net payable**      | Employees ko kul kitna dena hai (net salary + borrow given)    |

- Neeche employees ki list: **Employee**, **Gross salary**, **Present**, **Absent**, **Leave**, **Short hours**, **Overtime**, **Bonus**, **Borrow given**, **Borrow recovery**, **Other deductions**, **Net payable**, **Status**.
    - **Status** me **Calculated** ya **Adjusted** likha hota hai.
    - Peela **Check** nishan ho to us employee me kuch dekhne layak hai (jaise salary set nahi, ya kisi din attendance nahi).
    - Filter: search (**Employee name or ID**), **All departments**, **Adjusted and calculated**.
    - Har row me **Breakdown** button us employee ki pay sheet kholta hai.

### Employee ki pay sheet (Breakdown)

Ye page batata hai us employee ko ye rakam **kyun** mil rahi hai. Heading hoti hai "Why (naam) is paid ₹…".

Table me 4 column hain:

| Column                | Matlab                                                      |
| --------------------- | ----------------------------------------------------------- |
| **Description**       | Rakam kis cheez ki hai, aur chhote akshar me wo kaise nikli |
| **System calculated** | System ne khud kya nikala                                   |
| **Admin adjustment**  | Aapne haath se kitna joda / ghataya                         |
| **Final amount**      | Dono ko mila kar aakhri rakam. Salary isi se banti hai      |

Table ke hisse:

- **Salary** : salary ke hisse (Basic, HRA…). Mahine me salary badli ho to likha aata hai ki kitne revision ke hisaab se bati.
- **Additions** : **Overtime**, **Bonus**, **Other Earnings**.
- **Deductions** : **Attendance Deduction** (absent, half day, late, bina attendance ke din), **Unpaid Leave**, **Short Hours Deduction**, **Borrow Recovery** (har borrow ki alag line, number ke saath), **Other Deductions** (har mahine ki tay katauti aur ek baar ki katauti).
- **Net salary** : kamaai me se katauti ghata kar.
- **Borrow / advance given with this salary** : agar is salary ke saath naya borrow diya ja raha hai. Ye kamaai se alag dikhta hai.
- **Net payable** : employee ko asal me kitna dena hai = Net salary + naya borrow.

Har line ke neeche chhota note hota hai jo hisaab batata hai, jaise "1 absent day(s) x 1,000.00 per day".

Daayi taraf teen box:

- **Manual adjustments** : ab tak ke adjustment (kisne, kab, kyun) aur naya adjustment jodne ka form.
- **Salary used** : is period me kaun si salary lagi, **Rate per day** aur **Rate per hour**.
- **Attendance in this period** : working days, present, absent, leave, short hours, overtime. **Open calendar** se us mahine ki attendance khulti hai.

Payroll finalize ho to upar **Salary slip** button bhi aata hai.

## Kaam kaise karein

### 1. Mahine ka payroll shuru karna

1. **Payroll → Payroll** kholiye aur **Start payroll** dabaiye.
2. **Salary month** chuniye. System agla mahina khud suggest karta hai.
3. Chahein to **Notes** likhiye, phir **Start payroll** dabaiye.

Payroll **Draft** me ban jaata hai. Abhi kuch diya ya kata nahi gaya.

### 2. Calculate karna

1. Payroll page par **Calculate payroll** dabaiye.
2. System har current employee ki salary nikaalta hai aur status **Calculated** ho jaata hai.

Bahut zyada employees hon to hisaab peeche chalta hai; thodi der baad page dobara kholiye.

Payroll me wahi employee aate hain jo abhi kaam kar rahe hain aur jinki joining date period khatam hone tak ki hai. Jo employee company chhod chuka hai uska hisaab [Final Settlement](11-employee-exit-aur-final-settlement.md) se hota hai.

### 3. Check karna (Review)

1. Cards ke total dekhiye. Pichhle mahine se bahut farq ho to wajah dhoondhiye.
2. List me **Check** nishan wale employees ki **Breakdown** kholiye aur upar peele box me likhi baat padhiye.
3. Jin employees par shak ho unki pay sheet line-by-line dekhiye.
4. Chahein to **Start admin review** dabaiye, taaki sabko dikhe ki checking chal rahi hai.

### 4. Kuch galat mila to pehle asli jagah sudhariye, phir Recalculate

Agar attendance galat hai, leave approve nahi hui, bonus chhoot gaya ya salary galat hai, to pehle use uski apni jagah theek kijiye. Phir payroll page par **Recalculate** dabaiye.

Recalculate naye data ke hisaab se sab rakam dobara nikaalta hai. Aapke pehle kiye adjustment hat-te nahi; wo nayi rakam ke upar phir se lag jaate hain.

### 5. Haath se rakam badalna (Adjustment)

Jab system ki rakam sahi hai par aap kisi wajah se use badalna chahte hain:

1. Employee ki **Breakdown** kholiye.
2. **Manual adjustments** box me **Add an adjustment** ke neeche:
    - **What to adjust** : kis cheez ko badalna hai (Salary, Overtime, Bonus, Other Earnings, Attendance Deduction, Unpaid Leave, Short Hours Deduction, Borrow Recovery, Other Deductions).
    - **Amount** : kitna badalna hai. Plus ya minus dono chalte hain.
    - **Reason** : wajah likhna zaroori hai.
3. **Add adjustment** dabaiye.

**Plus / minus ka matlab:**

| Aap kya badal rahe hain                                            | Plus rakam (jaise 500) | Minus rakam (jaise -500) |
| ------------------------------------------------------------------ | ---------------------- | ------------------------ |
| Kamaai (Salary, Overtime, Bonus, Other Earnings)                   | ₹500 zyada milega      | ₹500 kam milega          |
| Katauti (Attendance Deduction, Borrow Recovery, Other Deductions…) | ₹500 zyada katega      | ₹500 kam katega          |

Form me rakam ke neeche yahi baat likhi bhi aati hai. Adjustment jodte hi **Admin adjustment** aur **Final amount** column badal jaate hain, employee par **Adjusted** likha aata hai aur payroll ka status **Adjusted** ho jaata hai.

Galat adjustment hatane ke liye uske saamne dustbin ka nishan dabaiye.

### 6. Is mahine borrow kitna katega, ye badalna

1. Employee ki **Breakdown** kholiye.
2. **What to adjust** me **Borrow Recovery** chuniye.
3. **Amount** me farq bhariye: kam katna hai to minus (jaise `-1000`), zyada katna hai to plus (jaise `2000`).
4. **Reason** likhiye aur **Add adjustment** dabaiye.

Niyam:

- Kul recovery employee ke kul baaki borrow se zyada nahi ho sakti. System rok dega.
- Recovery zero se neeche nahi ja sakti.
- Employee ke ek se zyada borrow hon aur aap kam kaat rahe hon, to har borrow ko pehle uski apni kisht tak hissa milta hai. Zyada kaat rahe hon to upar ki rakam sabse purane borrow me jaati hai.
- Jis borrow ki kisht is mahine bilkul nahi kati, uska plan agle mahine se aage chalta hai. Balance waisa hi rehta hai.

Borrow ki poori jaankari: [Borrow / Advance](08-borrow-advance.md).

### 7. Finalize aur lock karna

1. Sab check ho jaaye to **Finalize payroll** dabaiye.
2. Ek box khulega jisme **Employees**, **Net payable**, **Borrow given** aur **Borrow recovered** dikhenge. Dobara dekh lijiye.
3. **Finalize and lock** dabaiye.

Finalize hote hi:

- Payroll lock ho jaata hai. Rakam ab nahi badlegi, chahe baad me koi attendance ya salary badal de.
- Salary ke saath diye jaane wale naye borrow "diye gaye" maane jaate hain.
- Borrow ki kisht har borrow ke balance se ghat jaati hai.
- Is payroll ka overtime **Paid** ho jaata hai, aur iske bonus / deduction lock ho jaate hain.
- Har employee ki salary slip ban jaati hai.

### 8. Salary slip dekhna aur dena

1. Finalize hue payroll par **Salary slips** dabaiye, ya **Payroll → Salary Slips** kholiye.
2. Employee dhoondhne ke liye search box, aur mahina chunne ke liye **All finalized months** filter.
3. **View** : slip nayi tab me khulti hai. **Download** : PDF aapke computer / phone me save hoti hai.
4. PDF employee ko print karke ya bhej kar dijiye.

Slip me ye sab hota hai: company ka naam aur pata, salary ka mahina aur period, slip number, employee ki details, attendance aur working hours, **Earnings**, **Deductions**, **Borrow / advance** (is salary ke saath diya gaya aur is salary se kata gaya, alag-alag), **Net salary** aur **Net payable**.

Ek employee ki pay sheet par bhi **Salary slip** button se seedha uski slip khulti hai.

### 9. Finalize ke baad galti mili (Reopen)

1. Payroll page par **Reopen payroll** dabaiye.
2. **Reason for reopening** likhiye (kam se kam 5 akshar). Ye wajah aapke naam ke saath audit log me hamesha ke liye darj hoti hai.
3. **Reopen payroll** dabaiye.

Reopen karte hi:

- Payroll khul jaata hai aur status **Admin Review** ho jaata hai. Upar peeli patti me reopen ki tareekh aur wajah dikhti hai.
- Us payroll ne jo borrow diya aur kata tha wo ulta ho jaata hai (borrow ke ledger me **Reversal** entry).
- Us payroll ki salary slips wapas le li jaati hain.
- Overtime, bonus aur deduction phir se khul jaate hain.

Ab galti sudhariye, **Recalculate** dabaiye, check kijiye aur dobara **Finalize payroll** kijiye. Nayi slips ban jaayengi.

### 10. Payroll delete karna

Jo payroll finalize nahi hua use **Delete** se hata sakte hain (jaise galat mahina chun liya). Sirf us payroll ki nikaali hui rakam aur adjustment hat-te hain. Attendance, overtime, borrow aur salary ke record ko kuch nahi hota, aur aap us mahine ka payroll dobara shuru kar sakte hain.

### 11. Export aur reports

- Payroll page par **Export CSV** se us mahine ki poori list file me milti hai (Excel me khul jaati hai).
- **Payroll → Payroll Reports** me mahine-dar-mahine total aur ek mahine ka **Department breakdown** dikhta hai.
- Aur reports: [Reports aur Audit Log](12-reports-aur-audit-log.md).

## Example

**Rajesh Verma**, September 2026. Mahine ki salary ₹30,000 (Basic ₹18,000 + HRA ₹9,000 + Other Allowance ₹3,000). Company ka tarika "Fixed 30 days" hai, to ek din = 30,000 / 30 = ₹1,000.

Is mahine: 1 din absent, 4 ghante overtime (₹187.50 prati ghanta), ₹2,000 festival bonus, uniform ki ₹500 katauti, purane borrow BRW-00007 ki ₹3,000 kisht, aur ₹10,000 ka naya borrow BRW-00015 isi salary ke saath.

**Calculate ke baad pay sheet:**

| Description                                      | System calculated | Admin adjustment | Final amount |
| ------------------------------------------------ | ----------------- | ---------------- | ------------ |
| **Salary**                                       | ₹30,000           |                  | ₹30,000      |
| **Overtime** (4 hours x 187.50)                  | ₹750              |                  | ₹750         |
| **Bonus** (Festival bonus)                       | ₹2,000            |                  | ₹2,000       |
| **Attendance Deduction** (1 absent day x 1,000)  | -₹1,000           |                  | -₹1,000      |
| **Borrow Recovery** (BRW-00007)                  | -₹3,000           |                  | -₹3,000      |
| **Other Deductions** (Uniform)                   | -₹500             |                  | -₹500        |
| **Net salary** (kamaai ₹32,750 − katauti ₹4,500) |                   |                  | **₹28,250**  |
| **New Borrow / Advance** (BRW-00015)             | +₹10,000          |                  | +₹10,000     |
| **Net payable**                                  |                   |                  | **₹38,250**  |

Dhyan dijiye: ₹10,000 ka borrow kamaai (₹32,750) me nahi juda. Wo alag line me hai aur sirf **Net payable** me juda hai, kyunki ye udhaar hai jo Rajesh ko lautana hai.

**Adjustment:** Rajesh ne request ki ki is mahine borrow ki kisht ₹3,000 ki jagah ₹2,000 kaati jaaye. Aap **Borrow Recovery** par `-1000` ka adjustment jodte hain, Reason: "Employee request, medical kharch".

| Description         | System calculated | Admin adjustment | Final amount |
| ------------------- | ----------------- | ---------------- | ------------ |
| **Borrow Recovery** | -₹3,000           | +₹1,000          | -₹2,000      |

Aapne form me `-1000` bhara tha, yaani "₹1,000 kam kaato". Pay sheet ke **Admin adjustment** column me ye +₹1,000 dikhta hai, kyunki employee ko ₹1,000 zyada milega. Har line me **System calculated** aur **Admin adjustment** ko jodne par **Final amount** aata hai: -₹3,000 + ₹1,000 = -₹2,000. Ab katauti ₹3,500, **Net salary** ₹29,250 aur **Net payable** ₹39,250 ho jaata hai. Finalize karne par borrow BRW-00007 ke balance se sirf ₹2,000 ghatega.

## Mahine ke aakhir ki checklist

Payroll shuru karne se pehle:

1. **Attendance poori hai?** Koi din bina attendance ke na rahe. → [Attendance](05-attendance.md)
2. **Leave ke request** approve ya reject ho chuke hain? → [Leave](06-leave.md)
3. **Overtime** ki entry approve ho chuki hain? Sirf approve hua overtime payroll me aata hai. **Short hours** dekh liye? → [Short Hours aur Overtime](07-short-hours-aur-overtime.md)
4. **Bonus aur ek baar ki katauti** daal di? → [Salary, Bonus aur Deduction](09-salary-bonus-deduction.md)
5. **Salary** sabki set hai? Is mahine ka increment darj hai? → [Salary, Bonus aur Deduction](09-salary-bonus-deduction.md)
6. **Naye borrow** aur **cash me lautaya paisa** darj hai? → [Borrow / Advance](08-borrow-advance.md)
7. Jo employee is mahine **chhod gaye**, unka exit darj hai? → [Employee Exit aur Final Settlement](11-employee-exit-aur-final-settlement.md)

Phir payroll:

8. **Start payroll** → **Calculate payroll**.
9. Total aur **Check** nishan wale employees dekhiye.
10. Kuch sudhara ho to **Recalculate**.
11. Zaroori **adjustment** reason ke saath jodiye.
12. **Finalize payroll** → **Finalize and lock**.
13. **Salary slips** download karke employees ko dijiye.
14. **Export CSV** se bank / accounts ke liye list nikaaliye.

## Dhyan rakhne wali baatein

- **Ek mahine ka ek hi payroll.** Usi mahine ka doosra payroll shuru karne par system rok dega.
- **Finalize se pehle kuch pakka nahi hota.** Draft, Calculated, Admin Review aur Adjusted me na koi borrow diya jaata hai, na kata jaata hai, na slip banti hai.
- **Finalize ke baad payroll lock hai.** Uske baad attendance, salary ya settings me kiya badlav us payroll ko nahi chhuta. Badalna ho to reopen hi rasta hai.
- **Reopen halka kaam nahi hai.** Wo borrow ki entry ulti karta hai aur slips wapas leta hai. Agar slip employee ko di ja chuki hai to dobara finalize ke baad nayi slip dijiye.
- **Har adjustment ke saath wajah zaroori hai**, aur har adjustment ke saath naam aur samay darj hota hai. Adjustment zero nahi ho sakta, aur kisi rakam ko zero se neeche nahi le ja sakta.
- **Naya borrow adjustment se nahi badalta.** Salary ke saath diya ja raha borrow badalna ho to borrow ko hi badliye (cancel karke naya), payroll me nahi.
- **Borrow ki kisht salary se zyada nahi kat-ti.** Agar katauti ke baad pay kam bachi hai to kisht utni hi katti hai jitni bachi ho; pay sheet ke note me ye likha aata hai.
- **Attendance ki katauti gross salary se zyada nahi hoti.**
- **Finalize ke waqt "Recalculate the payroll and try again" aaye** to matlab calculate ke baad koi borrow badal gaya (jaise employee ne cash lauta diya). **Recalculate** dabakar dobara finalize kijiye.
- **Hisaab ke niyam** (ek din ki rakam kaise nikle, period kis tareekh se shuru ho, overtime aur short hours ka niyam, borrow ki limit) **Settings → Payroll** me hote hain. Dekhiye [Company Settings](13-company-settings.md).
- **Phone par:** list card ke roop me dikhti hai. Pay sheet me sirf **Description** aur **Final amount** column dikhte hain; jahan adjustment hai wahan naam ke neeche chhoti line me "System …, adjusted …" likha aata hai.

## Aksar pooche jane wale sawal

**Net salary aur Net payable me kya farq hai?**
**Net salary** = kamaai − katauti. **Net payable** = Net salary + is salary ke saath diya gaya naya borrow. Naya borrow na ho to dono barabar hote hain.

**Recalculate dabane se mere adjustment chale jaayenge?**
Nahi. Adjustment bane rehte hain aur nayi rakam ke upar lag jaate hain.

**Ek employee payroll me nahi dikh raha. Kyun?**
Ya to uski joining date is period ke baad ki hai, ya wo company chhod chuka hai. Chhod chuke employee ka hisaab Final Settlement me hota hai.

**Employee ki rakam ₹0 aa rahi hai.**
Aksar salary set nahi hoti. Pay sheet ke upar peela box aur **Salary used** box dekhiye, phir salary set karke **Recalculate** kijiye.

**Finalize ke baad attendance sudhaari, payroll me kyun nahi aaya?**
Finalize hua payroll lock hota hai. Sudhaar chahiye to **Reopen payroll** → **Recalculate** → dobara finalize.

**Salary slip list me nahi dikh rahi.**
Slip sirf finalize hue payroll ki banti hai. Agar payroll reopen kiya gaya hai to uski slips hat jaati hain aur dobara finalize karne par phir banti hain.
