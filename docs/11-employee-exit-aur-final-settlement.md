# Employee Exit aur Final Settlement

Jab koi employee company chhodta hai to do kaam hote hain. Pehla, use **exit** karna, jisse wo "past employee" ban jata hai. Doosra, uska **final settlement** banana, yaani aakhri hisaab: company ko use kitna dena hai, ya use company ko kitna lautana hai.

Is poore kaam me kuch bhi delete nahi hota. Employee ki attendance, salary, payroll, borrow aur documents sab record me bane rehte hain.

## Kahan milega

| Kaam                          | Kahan                                                                                 |
| ----------------------------- | ------------------------------------------------------------------------------------- |
| Employee ko exit karna        | **Employees** > **Active Employees** > employee ki profile > **Leave company** button |
| Chhod chuke employees ki list | **Employees** > **Past Employees**                                                    |
| Aakhri hisaab banana          | Menu me **Final Settlement**                                                          |

## Kaun use kar sakta hai

| Role              | Kya kar sakta hai                                                                                                              |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| **Company Admin** | Exit, reinstate, final settlement banana, finalize karna aur paid mark karna                                                   |
| **HR Manager**    | Company Admin jaisa hi, sab kuch                                                                                               |
| **Viewer**        | Sirf **Past Employees** ki list aur profile dekh sakta hai. **Leave company** button aur **Final Settlement** menu nahi dikhta |

## Screen par kya dikhta hai

### Past Employees

- Upar do cards: **Past employees** (ab tak kitne log chhod chuke) aur **Outstanding borrow** (un par kul kitna borrow baaki hai).
- Search box (**Name, ID or email**) aur **All departments** filter.
- Table ke columns: **Employee**, **Department**, **Joined**, **Last working day**, **Exit reason**, **Outstanding borrow**, **Final settlement**.
- Row ke aakhir me raseed jaisa chhota button us employee ka final settlement kholta hai.
- Naam par click karne se uski profile khulti hai.

**Final settlement** column ke status:

| Status          | Matlab                                                   |
| --------------- | -------------------------------------------------------- |
| **Not started** | Abhi hisaab khola hi nahi gaya                           |
| **Draft**       | Hisaab bana hai par pakka nahi hua. Abhi badal sakta hai |
| **Finalized**   | Hisaab pakka aur lock ho gaya. Ab nahi badlega           |
| **Paid**        | Paisa de diya gaya (ya employee ne lauta diya)           |

### Past employee ki profile

Profile par sabse upar **Exit details** ka dabba aata hai jisme **Last working day**, **Exit date**, **Exit type**, **Reason** aur notes hote hain. Uske saath final settlement ka status, aur do button: **Open final settlement** aur **Reinstate employee**.

Past employee ki profile par **Leave company**, **New borrow** aur work timing ka **Change** button nahi hota. Baaki sab (details, attendance, borrow, salary, activity) pehle jaisa dikhta hai.

### Final Settlement ki list

- Search box aur **All settlement statuses** filter (**Not started**, **Draft**, **Finalized**, **Paid**).
- Columns: **Employee**, **Last working day**, **Exit type**, **Outstanding borrow**, **Final settlement** (rakam), **Status**.
- Row ke aakhir me **Prepare** (agar hisaab abhi bana nahi) ya **Open** (agar ban chuka hai).

### Final settlement ka page

Upar likha hota hai ki hisaab kis period ka hai. Ye us payroll period ki shuruaat se lekar employee ke last working day tak hota hai. Saath me status aur button: **Recalculate** aur **Finalize settlement** (draft me), ya **Mark as paid** (finalize ke baad).

**Settlement statement** me har line ka matlab:

| Line                   | Matlab                                                                                                                                                                           | Asar                 |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| **Last salary**        | Aakhri period ki salary, last working day tak. Poore mahine ki salary me se last working day ke baad ke din aur absent, half day ya late ki katauti ghata kar ye rakam banti hai | Judta hai            |
| **Overtime**           | Approve hua overtime jo abhi tak diya nahi gaya                                                                                                                                  | Judta hai            |
| **Bonus**              | Bonus jo abhi tak kisi payroll me diya nahi gaya                                                                                                                                 | Judta hai            |
| **Other earnings**     | Koi aur kamai jo baaki hai                                                                                                                                                       | Judta hai            |
| **Unpaid leave**       | Bina salary wali chhutti ke dino ki katauti                                                                                                                                      | Ghat-ta hai          |
| **Short hours**        | Kam ghante kaam karne ki katauti (company ki setting ke hisaab se)                                                                                                               | Ghat-ta hai          |
| **Outstanding borrow** | Employee par jitna bhi borrow / advance baaki hai, poora ka poora                                                                                                                | Ghat-ta hai          |
| **Other deductions**   | Har mahine ki tay katauti aur alag se daali gayi katauti jo baaki hai                                                                                                            | Ghat-ta hai          |
| **Admin adjustment**   | Aapka haath se kiya gaya badlav, karan ke saath                                                                                                                                  | Judta ya ghat-ta hai |
| **Final settlement**   | Sabka jod. Yahi aakhri rakam hai                                                                                                                                                 | -                    |

Har line ke neeche chhote akshar me likha hota hai ki wo rakam kaise bani (jaise kitne din x kitna rate). Table ke neeche ek saaf line hoti hai:

- "The company pays (naam) ₹..." yaani company ko dena hai, ya
- "(naam) owes the company ₹..." yaani employee ko lautana hai (rakam laal me, minus ke saath), ya
- "Nothing is payable either way." yaani hisaab barabar.

Right side me: **Employee** (department, joining date, last working day, exit type, exit reason), **Final period** (us period ki attendance: working days, present, absent, half days, paid leave, unpaid leave, required / worked / short hours, overtime, aur mahine ki salary, per-day rate aur hourly rate), aur finalize ke baad **Status** (kab finalize hua, kab paid hua).

Agar upar peela dabba aaye to use zaroor padhein. Usme chetavni hoti hai, jaise "working day(s) have no attendance and are treated as absent" (kuch dino ki attendance mark nahi hai, unhe absent maana gaya) ya "No salary is set for this period" (salary set nahi hai).

## Kaam kaise karein

### Exit se pehle ki tayyari

Hisaab sahi bane, iske liye exit se pehle ye dekh lein:

1. Last working day tak ki **attendance** mark ho. Agar company me attendance haath se (manual) mark hoti hai to jo din mark nahi hain wo absent maane jayenge.
2. Jo **overtime** dena hai wo approve ho chuka ho.
3. Koi **bonus** ya **deduction** dena / kaatna hai to wo daal diya ho.
4. Koi **leave** request pending hai to uska faisla ho chuka ho.

### Employee ko exit karna

1. **Employees** > **Active Employees** me employee ka naam kholein.
2. Upar right me laal **Leave company** button dabayein.
3. Khulne wale form me bharein:

| Field                  | Kya bharna hai                                                                                                                                        |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Last working day** * | Kaam ka aakhri din. Salary isi din tak banti hai                                                                                                      |
| **Exit date** *        | Jis tareekh par exit darj karna hai                                                                                                                   |
| **Exit type** *        | **Resignation** (khud chhoda), **Termination** (company ne hataya), **Retirement**, **Contract Ended**, **Absconding** (bina bataye gayab), **Other** |
| **Reason**             | Chhota karan, jaise "Doosre shehar chale gaye"                                                                                                        |
| **Notes**              | Koi aur jaankari                                                                                                                                      |

4. Agar employee par borrow baaki hai to form me peeli line dikhegi ki kitna baaki hai aur wo final settlement me wapas liya jayega.
5. **Mark as past employee** dabayein.

Ab employee **Past Employees** me chala jata hai aur aage ke kisi naye payroll me nahi aata. Dono tareekhein joining date se pehle ki nahi ho sakti.

### Final settlement banana

1. Menu me **Final Settlement** kholein. (Ya past employee ki profile par **Open final settlement**.)
2. Employee ki row me **Prepare** dabayein.
3. System khud poora hisaab bana kar dikhata hai. Status **Draft** hota hai.
4. Har line aur uske neeche ka chhota hisaab dhyan se dekhein. Upar peela chetavni dabba ho to padhein.

Jab tak hisaab draft hai, page kholne par wo har baar naye data se dobara banta hai.

### Hisaab me kuch galat ho to

1. Galti jahan hai wahan theek karein. Jaise attendance mark karna, overtime approve karna, bonus ya deduction daalna.
2. Settlement page par wapas aakar **Recalculate** dabayein.
3. Naya hisaab dikh jayega.

### Haath se rakam jodna ya ghatana (Admin adjustment)

Kabhi kuch aisa hota hai jo system ke hisaab me nahi aata, jaise notice period poora na karne ki katauti, ya koi extra rakam dena.

1. Settlement page par **Admin adjustment** hisse me jayein.
2. **Adjustment amount** me rakam likhein. Jodna hai to seedha number (jaise 2000). Ghatana hai to minus ke saath (jaise -5000). Koi badlav nahi to 0.
3. **Reason** me karan likhein. Rakam 0 nahi hai to karan zaroori hai.
4. Chahein to **Notes** likhein.
5. **Save adjustment** dabayein.

Statement me **Admin adjustment** ki line aur neeche ka **Final settlement** turant badal jata hai.

### Settlement finalize karna

1. Sab kuch sahi lage to upar **Finalize settlement** dabayein.
2. Pakka karne wale dabbe me rakam aur borrow ki jaankari padh kar **Finalize settlement** dabayein.

Finalize karte hi:

- Hisaab lock ho jata hai. Ab na **Recalculate** hoga, na adjustment.
- Employee ka poora baaki borrow wapas liya hua maan liya jata hai aur uske borrow record band ho jate hain.
- Isme shamil overtime "paid" ho jata hai.
- Status **Finalized** ho jata hai.

### Paid mark karna

1. Jab paisa sach me de diya jaye (ya employee lauta de), settlement page par **Mark as paid** dabayein.
2. **Mark as paid** se pakka karein.

Status **Paid** ho jata hai aur tareekh, samay darj ho jata hai.

### Galti se exit ho gaya ho to (Reinstate)

1. **Past Employees** se employee ki profile kholein.
2. **Exit details** me **Reinstate employee** dabayein.
3. **Reinstate employee** se pakka karein.

Employee wapas **Active** ho jata hai, exit ki jaankari hat jati hai aur draft settlement (agar bana tha) hata diya jata hai.

## Example

Rajesh Kumar ki gross salary ₹30,000 mahina hai. Company "Fixed 30 days" ka hisaab use karti hai, to ek din ka rate ₹1,000 hai. Rajesh ne resign kiya aur unka last working day 20 November 2026 hai. Un par ₹9,000 ka borrow baaki hai.

1. Meera (HR Manager) Rajesh ki profile par **Leave company** dabati hain. **Last working day** 20 Nov 2026, **Exit type** Resignation, **Reason** "Better opportunity". Form me peeli line dikhti hai ki ₹9,000 borrow baaki hai. Wo **Mark as past employee** dabati hain.
2. **Final Settlement** me Rajesh ki row par **Prepare** dabati hain. Statement aisa banta hai:

| Line                                                                                               | Rakam       |
| -------------------------------------------------------------------------------------------------- | ----------- |
| Last salary (₹30,000 me se 21 se 30 Nov ke 10 din ke ₹10,000 aur 1 din absent ke ₹1,000 ghata kar) | + ₹19,000   |
| Overtime (6 ghante)                                                                                | + ₹1,125    |
| Bonus                                                                                              | ₹0          |
| Unpaid leave (1 din)                                                                               | - ₹1,000    |
| Short hours                                                                                        | ₹0          |
| Outstanding borrow                                                                                 | - ₹9,000    |
| Other deductions                                                                                   | ₹0          |
| Admin adjustment                                                                                   | ₹0          |
| **Final settlement**                                                                               | **₹10,125** |

3. Rajesh ne office ka ID card nahi lautaya. Meera **Admin adjustment** me **Adjustment amount** -500 aur **Reason** "ID card not returned" likh kar **Save adjustment** dabati hain. **Final settlement** ₹9,625 ho jata hai aur neeche likha aata hai "The company pays Rajesh Kumar ₹9,625.00."
4. Aarav (Company Admin) dekh kar **Finalize settlement** dabate hain. Rajesh ka ₹9,000 ka borrow band ho jata hai.
5. Paisa bank se bhejne ke baad Meera **Mark as paid** dabati hain.

## Dhyan rakhne wali baatein

- **Exit ke baad employee naye payroll me nahi aata.** Uske aakhri period ki salary final settlement se di jati hai, payroll se nahi.
- **Agar us period ka payroll pehle hi finalize ho chuka tha**, to salary di ja chuki hai. Tab settlement me neeli line aati hai ki salary pehle hi payroll se di gayi, aur **Last salary**, **Unpaid leave** aur **Short hours** me ₹0 dikhta hai. Sirf wahi cheezein settle hoti hain jo us payroll me nahi thi, jaise baaki borrow.
- **Borrow poora wapas liya jata hai.** Settlement me mahine ki kist nahi, employee par jitna bhi baaki hai sab ek saath ghat-ta hai.
- **Rakam minus me aa sakti hai.** Iska matlab employee ko company ko paisa lautana hai. Tab **Mark as paid** ka matlab hai ki employee ne wo rakam lauta di.
- **Finalize ke baad wapasi nahi.** Finalized settlement ko dobara khola, badla ya recalculate nahi kiya ja sakta. Finalize se pehle achhe se jaanch lein.
- **Finalize ke baad Reinstate bhi nahi hota.** **Reinstate employee** button sirf tab tak dikhta hai jab tak settlement shuru nahi hua ya draft hai.
- **Paid mark karne se pehle Finalize zaroori hai.**
- **Mark nahi ki gayi attendance absent maani ja sakti hai.** Jis company me attendance haath se (manual) mark hoti hai wahan bina mark wale din absent gine jate hain. Isliye exit se pehle last working day tak attendance poori kar lein.
- **Sirf approve hua overtime** settlement me aata hai.
- **Adjustment bina karan ke save nahi hota** (jab rakam 0 na ho).
- **Employee delete nahi hota.** Past employee ka poora record hamesha dekha ja sakta hai.
- Exit, reinstate, adjustment, finalize aur paid, sab **Audit Logs** me darj hota hai.

**Phone par:** **Past Employees** aur **Final Settlement** ki list cards me dikhti hai. Settlement page par statement upar aur employee ki jaankari uske neeche aa jati hai. **Leave company** ka form poori screen par khulta hai aur fields ek ke neeche ek hote hain.

## Aksar pooche jane wale sawal

**Final Settlement ki list me employee kyun nahi dikh raha?**
Wahan sirf past employees aate hain. Pehle employee ki profile par **Leave company** karein.

**Last working day galat daal diya, ab kya karun?**
Agar settlement abhi finalize nahi hua to employee ko **Reinstate employee** se wapas layein aur sahi tareekh ke saath dobara **Leave company** karein.

**Settlement finalize ho gaya aur baad me galti mili. Ab?**
Finalized settlement badla nahi ja sakta. Isliye finalize se pehle har line jaanch lein. Fark ki rakam ka len-den alag se karna hoga aur uski jaankari apne record me rakhni hogi.

**Last salary ₹0 kyun dikh rahi hai?**
Ya to us period ka payroll pehle hi finalize hokar salary di ja chuki hai (upar neeli line dikhegi), ya employee ki salary set nahi hai (upar peeli chetavni dikhegi).

**Kya exit ke baad bhi employee ka purana data dekh sakte hain?**
Haan. **Past Employees** se profile kholein. Attendance calendar, salary, borrow aur activity sab wahin hai. Purani salary slips **Payroll** > **Salary Slips** me milti hain.

**Employee ne notice period poora nahi kiya, uski katauti kaise karun?**
**Admin adjustment** me minus rakam daalein aur **Reason** me likhein, jaise "Notice period shortfall".
