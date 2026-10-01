# Borrow / Advance

Ye module us paise ka hisaab rakhta hai jo company ne employee ko udhaar (borrow) ya salary advance ke roop me diya hai. Kitna diya, kitna wapas aaya aur kitna abhi baaki hai, ye sab har borrow ke liye alag-alag dikhta hai.
Har borrow ka apna number, apna balance, apni kisht (installment) ki list aur apni poori history hoti hai. Ek employee ke ek se zyada borrow ho sakte hain aur wo kabhi aapas me mix nahi hote.

## Kahan milega

- Left menu → **Employee Finance** → **Borrow / Advance** : saare borrow ki list aur upar summary.
- Left menu → **Employee Finance** → **Borrow Recovery** : har diya gaya aur wapas aaya paisa, ek hi jagah (ledger).
- Employee ki profile par **Borrow / advance** card: us employee ke saare borrow. Wahin **New borrow** button bhi hai.
- **Add Employee** form me **Existing borrow / advance** section: joining ke time pehle se chala aa raha borrow.

## Kaun use kar sakta hai

| Role              | Kya kar sakta hai                                                                                 |
| ----------------- | ------------------------------------------------------------------------------------------------- |
| **Company Admin** | Sab kuch: dekhna, naya borrow, recovery, cancel                                                   |
| **HR Manager**    | Sab kuch: dekhna, naya borrow, recovery, cancel                                                   |
| **Viewer**        | Sirf dekh sakta hai. **New borrow**, **Record recovery** aur **Cancel borrow** button nahi dikhte |

Agar aapki company ne apna alag role banaya hai, to **Settings → Roles & Permissions** me ye do permission dekhiye: **View borrow, overtime, bonuses and deductions** (dekhne ke liye) aur **Manage borrow, overtime, bonuses and deductions** (kaam karne ke liye).

## Screen par kya dikhta hai

### Borrow / Advance page

Sabse upar 8 card hote hain:

| Card                      | Matlab                                                                       |
| ------------------------- | ---------------------------------------------------------------------------- |
| **Total borrowed**        | Ab tak kul kitna borrow / advance diya gaya                                  |
| **Total recovered**       | Usme se kitna wapas aa chuka (salary se kata ya employee ne lautaya)         |
| **Outstanding**           | Employees par abhi kitna baaki hai                                           |
| **Employees with borrow** | Kitne employees par abhi balance baaki hai                                   |
| **Active borrows**        | Kitne borrow abhi chal rahe hain                                             |
| **Fully recovered**       | Kitne borrow poore wapas aa chuke                                            |
| **Pending disbursement**  | Kitne borrow aane wali salary ke saath diye jaane hain (abhi diye nahi gaye) |
| **Overdue**               | Kitne borrow ki pichhle kisi mahine ki kisht abhi tak nahi kati              |

**Outstanding**, **Active borrows**, **Fully recovered** aur **Pending disbursement** card par click karne se neeche ki list usi hisaab se filter ho jaati hai (jaise **Fully recovered** par click karne se sirf poore wapas aa chuke borrow).

Uske neeche do box:

- **Highest outstanding** : jin 5 employees par sabse zyada paisa baaki hai (unke saare active borrow jod kar).
- **Upcoming deductions** : is mahine aur agle mahine jo kishtein salary se katni hain.

Phir filter: search box (**Reference, employee name or ID**), **All statuses** aur **New and existing**.

Aur aakhir me borrow ki list in column ke saath: **Borrow** (number, jaise BRW-00012), **Employee**, **Kind** (New / Existing), **Borrow given**, **Recovered**, **Outstanding**, **Monthly deduction**, **Date**, **Status**.

### Borrow ka detail page

List me borrow number par click karne se us ek borrow ka poora page khulta hai:

- Upar status, aur **Record recovery** / **Cancel borrow** button (jab allowed ho).
- Teen card: **Borrow given**, **Recovered**, **Outstanding**.
- **Recovery progress** : kitna percent wapas aa chuka, ek bar ke roop me.
- **Details** : Kind, Borrow date, Payout, Original amount, Monthly deduction, Deductions start in, Reason, Source or reference, Notes.
- **Installment schedule** : kisht ki list. Jo kat chuki wo **Recovered**, jo baaki hai wo **Pending**. Salary se kati kisht ke aage **via payroll** likha hota hai.
- **Ledger** : is borrow ke balance me jo bhi badlav hua, purane se naye ke order me.

### Status ka matlab

| Status                   | Matlab                                                                                                                          |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------- |
| **Pending Disbursement** | Borrow ban gaya hai par paisa abhi diya nahi gaya. Chuni hui salary ke saath diya jaayega. Tab tak ye outstanding me nahi ginta |
| **Active**               | Paisa diya ja chuka hai aur recovery chal rahi hai                                                                              |
| **Fully Recovered**      | Poora paisa wapas aa gaya, ab kuch baaki nahi                                                                                   |
| **Cancelled**            | Borrow radd kar diya gaya. Iska balance zero maana jaata hai                                                                    |

### Ledger ki entry ka matlab

| Entry                         | Matlab                                                                    |
| ----------------------------- | ------------------------------------------------------------------------- |
| **Opening Balance**           | Joining ke time jo balance pehle se baaki tha (existing borrow)           |
| **Borrow Given**              | Employee ko paisa diya gaya                                               |
| **Recovery**                  | Paisa wapas aaya: salary se kata ya employee ne khud lautaya              |
| **Reversal**                  | Pehle ki koi entry ulti ki gayi, jaise payroll dobara khola (reopen) gaya |
| **Final Settlement Recovery** | Employee ke company chhodne par final settlement me se kata gaya          |

**Amount** column me `+` ka matlab balance badha (paisa diya), `-` ka matlab balance ghata (paisa wapas aaya). **Balance after** batata hai us entry ke baad kitna baaki raha. **By** me us user ka naam hota hai jisne entry ki.

## Kaam kaise karein

### 1. Naya borrow dena (New borrow)

1. **Employee Finance → Borrow / Advance** kholiye aur **New borrow** dabaiye.
2. **Employee** chuniye.
3. **What kind of borrow is this?** me **New borrow** chuniye.
4. **Borrow amount** aur **Borrow date** bhariye. **Reason** likhna achha rehta hai (jaise "Medical expenses").
5. **How the money is paid out** me chuniye paisa kaise diya gaya:
    - **Given directly** : paisa haath me ya bank se alag se de diya gaya. Borrow turant **Active** ho jaata hai.
    - **Add borrow with salary** : paisa kisi mahine ki salary ke saath diya jaayega. Niche **Salary month** chuniye. (Iske baare me alag se neeche padhiye.)
6. **Recovery from salary** me batayiye wapas kaise aayega:
    - **Monthly deduction** : har mahine salary se kitna katega, ya
    - **Number of installments** : kitni kisht me wapas lena hai (system khud mahine ki rakam nikaal lega).
    - Dono bhare hon to **Monthly deduction** maana jaata hai.
    - **Deductions start in** : kis mahine ki salary se katna shuru hoga.
7. Bharte hi neeche ek line dikhti hai, jaise "₹18,000 recovered at ₹3,000 a month over 6 installments, the last one in February 2027". Isse plan check kar lijiye.
8. Chahein to **Source or reference** (jaise voucher number) aur **Notes** bhariye.
9. **Save borrow** dabaiye. Borrow ka detail page khul jaayega aur use ek number mil jaayega (jaise BRW-00012).

Agar employee paisa khud cash me lautayega aur salary se kuch nahi katna, to **Monthly deduction** me `0` likhiye. Tab koi kisht nahi banegi aur recovery aap haath se record karenge.

### 2. Joining ke time pehle se chala aa raha borrow (Existing borrow)

Jab employee join karte waqt pehle se company ka paisa lautana baaki ho (jaise purana advance), to use existing borrow ke roop me darj kiya jaata hai. Isme company koi paisa nahi deti, sirf baaki balance record hota hai.

**Tarika A — employee add karte waqt:**

1. **Employees → Add Employee** form me neeche **Existing borrow / advance** section me **Has an existing borrow** on kijiye.
2. **Original borrow amount** bhariye (shuru me kul kitna tha).
3. **Outstanding balance at joining** me likhiye aaj kitna baaki hai. Agar abhi tak kuch nahi lautaya to khaali chhod dijiye.
4. **Original borrow date** (khaali chhodne par joining date maani jaati hai), **Monthly deduction** ya **Number of installments**, aur **Start deducting from** bhariye.
5. Employee save karte hi borrow bhi ban jaata hai.

**Tarika B — baad me:**

1. **Borrow / Advance → New borrow** kholiye.
2. **What kind of borrow is this?** me **Existing borrow at joining** chuniye.
3. **Original borrow amount**, **Outstanding balance at joining** aur **Original borrow date** bhariye. Baaki step naye borrow jaise hi hain.

Existing borrow ke **Payout** me "Carried in at joining (nothing paid out)" likha aata hai aur ledger ki pehli entry **Opening Balance** hoti hai.

### 3. Borrow salary ke saath dena (Add borrow with salary)

1. Naya borrow banate waqt **Add borrow with salary** chuniye aur **Salary month** chuniye.
2. Save karne par borrow ka status **Pending Disbursement** rehta hai. Abhi employee par kuch baaki nahi mana jaata.
3. Us mahine ka payroll calculate karne par ye rakam employee ki pay sheet me alag line **New Borrow / Advance** ke roop me dikhti hai. Ye salary ki kamaai (earnings) me nahi judti, sirf **Net payable** me judti hai.
4. Jab wo payroll **Finalize** hota hai tabhi paisa "diya gaya" mana jaata hai: status **Active** ho jaata hai aur ledger me **Borrow Given** entry (via payroll) ban jaati hai.

Dhyan rahe: agar us mahine ka payroll pehle se calculate ho chuka hai to payroll page par **Recalculate** dabaiye, tabhi naya borrow usme dikhega. Payroll ke baare me poori jaankari: [Payroll aur Salary Slip](10-payroll-aur-salary-slip.md).

### 4. Salary se recovery (apne aap)

Aapko har mahine kuch nahi karna hota:

1. Jis mahine se **Deductions start in** hai, us mahine ke payroll me har active borrow ki mahine ki kisht apne aap **Borrow Recovery** line ban kar aa jaati hai. Har borrow ki alag line hoti hai, borrow number ke saath.
2. Payroll **Finalize** hote hi ye rakam borrow ke balance se ghat jaati hai, kisht **Recovered** ho jaati hai aur ledger me **Recovery** entry (via payroll) ban jaati hai.
3. Aakhri kisht ke baad borrow apne aap **Fully Recovered** ho jaata hai.

Agar kisi mahine kam ya zyada katna hai to wo payroll ki pay sheet me **Borrow Recovery** par adjustment karke hota hai. Tarika: [Payroll aur Salary Slip](10-payroll-aur-salary-slip.md) me "Is mahine borrow kitna katega, ye badalna".

### 5. Cash / bank se wapas aaya paisa darj karna (Record recovery)

Jab employee salary ke bahar paisa lautaye (cash, bank transfer):

1. Borrow ke detail page par **Record recovery** dabaiye. Ya **Borrow Recovery** page par **Record recovery** dabakar **Borrow** chuniye.
2. Upar dikhega **Outstanding before this recovery** (abhi kitna baaki hai).
3. **Amount recovered** bhariye. Niche turant dikhega kitna baaki rahega.
4. **Date** chuniye (aaj ya pichhli koi tareekh; aage ki tareekh nahi chalti).
5. **Notes** me likhiye paisa kaise aaya (jaise "cash returned").
6. **Record recovery** dabaiye.

Balance turant ghat jaata hai aur baaki kishtein naye balance ke hisaab se dobara ban jaati hain.

### 6. Borrow cancel karna

1. Borrow ke detail page par **Cancel borrow** dabaiye aur confirm kijiye.
2. Status **Cancelled** ho jaata hai aur baaki kishtein hat jaati hain.

Ye button tabhi dikhta hai jab borrow par abhi tak koi recovery nahi hui ho. Jo borrow finalize ho chuki salary ke saath diya ja chuka hai, wo yahan se cancel nahi hota.

### 7. Poora hisaab dekhna (Borrow Recovery page)

**Employee Finance → Borrow Recovery** par saare borrow ki saari entry ek saath dikhti hain.

- Upar do card: **Recovered this month** aur **Recovered last month**.
- Filter: search, **All entry types**, **From** aur **To** tareekh.
- Column: **Date**, **Employee**, **Borrow**, **Entry**, **Amount**, **Balance after**, **Notes**, **By**.
- Borrow number par click karke seedha us borrow ke page par ja sakte hain.

## Example

**Nisha Patel** ko 5 August 2026 ko ₹18,000 ka borrow diya gaya (**Given directly**). **Monthly deduction** ₹3,000, **Deductions start in** September 2026. Plan: 6 kisht, aakhri February 2027.

| Kab                        | Kya hua                                            | Rakam    | Baaki (Outstanding)      |
| -------------------------- | -------------------------------------------------- | -------- | ------------------------ |
| 5 Aug 2026                 | Borrow Given                                       | +₹18,000 | ₹18,000                  |
| September payroll finalize | Recovery (via payroll)                             | -₹3,000  | ₹15,000                  |
| 12 Oct 2026                | Nisha ne ₹5,000 cash lautaya (**Record recovery**) | -₹5,000  | ₹10,000                  |
| October payroll finalize   | Recovery (via payroll)                             | -₹3,000  | ₹7,000                   |
| November payroll finalize  | Recovery (via payroll)                             | -₹3,000  | ₹4,000                   |
| December payroll finalize  | Recovery (via payroll)                             | -₹3,000  | ₹1,000                   |
| January payroll finalize   | Recovery (via payroll)                             | -₹1,000  | ₹0 → **Fully Recovered** |

Jod: 3,000 + 5,000 + 3,000 + 3,000 + 3,000 + 1,000 = ₹18,000. Cash lautane ki wajah se borrow ek mahina pehle (January me) khatam ho gaya, aur aakhri kisht apne aap sirf ₹1,000 ki bani.

**Existing borrow ka example:** Imran Shaikh 1 September 2026 ko join hua. Uska purana advance ₹20,000 ka tha jisme se ₹12,000 abhi baaki hai. **Original borrow amount** ₹20,000, **Outstanding balance at joining** ₹12,000, **Monthly deduction** ₹2,000, shuruaat October 2026 se. System sirf ₹12,000 ki recovery karega: 6 kisht, aakhri March 2027.

## Dhyan rakhne wali baatein

- **Baaki se zyada recovery nahi ho sakti.** Aap outstanding se zyada rakam record karenge to system rok dega. Wajah: employee se uske udhaar se zyada paisa kabhi nahi katna chahiye, warna hisaab galat ho jaayega aur employee ko paisa lautana padega. Salary se katne wali kisht bhi isi wajah se kabhi baaki balance se badi nahi banti.
- **Salary bhi zero se neeche nahi jaati.** Agar kisi mahine employee ki pay kam hai to borrow ki kisht utni hi katti hai jitni pay me bachi ho. Company chahe to **Settings → Payroll** me **Maximum recovery (% of pay)** bhi set kar sakti hai.
- **Har borrow alag hai.** Ek employee ke do borrow hain to dono ki kisht alag line me katti hai aur dono ka balance alag chalta hai.
- **Borrow salary ki kamaai nahi hai.** Salary ke saath diya gaya borrow pay sheet aur salary slip me earnings se alag dikhta hai.
- **Borrow save hone ke baad edit nahi hota.** Amount, monthly deduction ya start month badalne ka option nahi hai. Galti ho gayi ho aur abhi koi recovery nahi hui, to borrow **Cancel** karke naya banaiye. Sirf kisi ek mahine ki rakam badalni ho to payroll me adjustment kijiye.
- **Ledger ki entry kabhi edit ya delete nahi hoti.** Sudhaar hamesha nayi entry ke roop me hota hai (jaise **Reversal**).
- **Recovery ho chuki ho to borrow cancel nahi hota.**
- **Payroll reopen karne par** us payroll ne jo borrow diya ya kata tha wo ulta ho jaata hai (ledger me **Reversal** entry). Dobara finalize karne par phir se darj hota hai.
- **Add borrow with salary** me aisa **Salary month** chuniye jiska payroll abhi finalize nahi hua hai. Finalize ho chuke mahine ko chunne par system borrow save nahi karega aur batayega ki koi aage ka mahina chuniye (ya us mahine ka payroll reopen kijiye).
- **Salary se apne aap katna band bhi ho sakta hai.** Agar company ne **Settings → Payroll** me **Deduct the monthly installment automatically** band kiya hai, to kisht apne aap nahi katti; tab recovery payroll adjustment ya **Record recovery** se hi hoti hai.
- **Employee company chhod de** to baaki poora borrow final settlement me kata jaata hai. Dekhiye [Employee Exit aur Final Settlement](11-employee-exit-aur-final-settlement.md).
- **Phone par:** list table ki jagah card ke roop me dikhti hai, har borrow ek card. Saari jaankari wahi rehti hai.

## Aksar pooche jane wale sawal

**Ek employee ko doosra borrow de sakte hain jab pehla abhi chal raha ho?**
Haan. **New borrow** se naya borrow banaiye. Dono alag number ke saath alag chalenge aur dono ki kisht salary se alag-alag kategi.

**Overdue ka kya matlab hai?**
Kisi pichhle mahine ki kisht abhi tak **Pending** hai, yaani us mahine salary se nahi kati (jaise us mahine ka payroll finalize nahi hua, ya recovery kam kar di gayi thi). Borrow kholkar **Installment schedule** dekhiye.

**Employee ne ek saath poora paisa lauta diya, ab kya karein?**
**Record recovery** me poori outstanding rakam bhariye. Borrow turant **Fully Recovered** ho jaayega aur aage salary se kuch nahi katega.

**Is mahine employee ne kaha kisht mat kaato. Kaise karein?**
Us mahine ke payroll me employee ki pay sheet kholkar **Borrow Recovery** par minus me adjustment kijiye (reason ke saath). Borrow ka balance waise hi rahega aur plan agle mahine se aage chalega.

**Pending Disbursement wala borrow outstanding me kyun nahi dikhta?**
Kyunki paisa abhi diya hi nahi gaya. Jab us mahine ka payroll finalize hoga tab wo **Active** hoga aur outstanding me ginega.

**Borrow ki report kahan milegi?**
**Reports** page par. Dekhiye [Reports aur Audit Log](12-reports-aur-audit-log.md).
