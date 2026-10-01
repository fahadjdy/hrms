# Dashboard

Dashboard company ki poori tasveer ek hi screen par dikhata hai: kitne log hain, aaj kaun aaya, is mahine ka payroll kitna hai, kitna borrow baaki hai. Yahan kuch bhi badla nahi jata, sirf dekha jata hai. Har number ke peeche ka poora hisaab uske apne module me milta hai.

## Kahan milega

Menu me sabse upar **Dashboard**. Login karte hi company ke user ko yahi screen dikhti hai. Upar left ke logo par click karne se bhi yahin aate hain.

## Kaun use kar sakta hai

Company ka har user: Company Admin, HR Manager aur Viewer. Super Admin ke paas ye dashboard nahi hota, use **Companies** ki list dikhti hai.

## Screen par kya dikhta hai

Upar se neeche is kram me:

1. **Heading**: "Dashboard" aur uske neeche likha hota hai ki kis tareekh se kis tareekh tak ka data dikh raha hai.
2. **Filters ki ek line**: **Period**, **Department**, **Employee**, **Employee status**.
3. **Mukhya numbers ke cards** (12 cards).
4. **Charts aur lists**.

### Mukhya numbers ke cards

Kisi bhi card par click karne se uska poora page khul jata hai.

| Card                   | Matlab                                                                                                                                | Click karne par                |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------ |
| **Total Employees**    | Record me jitne bhi log hain, abhi wale aur chhod chuke dono                                                                          | Active Employees               |
| **Active Employees**   | Jo abhi company me kaam kar rahe hain. Neeche likha aata hai ki is mahine kitne join hue                                              | Active Employees               |
| **Past Employees**     | Jo company chhod chuke hain. Unka record rakha jata hai                                                                               | Past Employees                 |
| **Present Today**      | Aaj kitne log aaye. Isme late, short hours, WFH aur half day wale bhi gine jate hain                                                  | Daily Attendance               |
| **Absent Today**       | Aaj jinhe Absent mark kiya gaya                                                                                                       | Daily Attendance (sirf absent) |
| **On Leave**           | Aaj paid ya unpaid leave par                                                                                                          | Leave Records                  |
| **Late Today**         | Aaj jo chhoot ke samay (grace period) ke baad aaye                                                                                    | Daily Attendance (sirf late)   |
| **Payroll - (mahina)** | Us mahine ke payroll ka net payable, yaani kul kitna dena hai. Neeche payroll ka status aur pichhle payroll se kitne % upar ya neeche | Payroll                        |
| **Total Overtime**     | Chune hue period me approve ya pay hua overtime, rupaye me. Pichhle utne hi lambe period se tulna bhi dikhti hai                      | Overtime                       |
| **Total Borrowed**     | Ab tak diya gaya kul borrow / advance                                                                                                 | Borrow / Advance               |
| **Borrow Outstanding** | Employees par abhi kitna baaki hai                                                                                                    | Borrow / Advance (sirf active) |
| **Total Deductions**   | Us payroll me kati kul rakam                                                                                                          | Payroll Reports                |

Agar abhi tak koi payroll calculate nahi hua to payroll wala card **Current Month Payroll** naam se dikhta hai aur likha hota hai "No payroll has been run yet".

### Charts aur lists

| Hissa                         | Kya dikhata hai                                                                                                                                                                                                                                                                                                 |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Attendance overview**       | Chune hue period ke attendance din, status ke hisaab se ek rangeen patti me: **Present**, **WFH**, **Half Day**, **Leave**, **Absent**, **Weekly Off**. Neeche **Attendance rate** (%) aur **Late arrivals** (kitne din late).                                                                                  |
| **Salary expense trend**      | Pichhle 12 mahino me har payroll ka net salary kharch. Isme borrow diya gaya paisa shamil nahi hota.                                                                                                                                                                                                            |
| **Working hours**             | **Required hours**, **Worked hours**, **Short hours**, **Overtime hours**, kitne % zaroori ghante poore hue, aur department ke hisaab se kaam ke ghante.                                                                                                                                                        |
| **Borrow overview**           | **Total borrowed**, **Total recovered**, **Outstanding**, **Recovered this month**, **Employees with borrow**, aur mahine dar mahine **Borrow given** aur **Borrow recovered** ki tulna.                                                                                                                        |
| **Department payroll**        | Us payroll me har department ka net salary.                                                                                                                                                                                                                                                                     |
| **Deduction summary**         | Us payroll me salary kyun kati: **Attendance deductions**, **Borrow recovery**, **Short-hours deductions**, **Other deductions**. Alag line me **New borrow / advance given** (ye salary nahi, advance hai) aur sabse neeche **Net payable**. Neeche **Open ... payroll** link se seedha wo payroll khulta hai. |
| **Department distribution**   | Har department me abhi kitne employees hain.                                                                                                                                                                                                                                                                    |
| **Employee status**           | **Active**, **Past**, **New this month**, **Exited this month**, **On probation**, **On notice** ki ginti, aur abhi ke employees me male / female ka bantwara.                                                                                                                                                  |
| **Overtime trend**            | Har mahine attendance me darj overtime ke ghante.                                                                                                                                                                                                                                                               |
| **Short hours trend**         | Har mahine zaroori ghanto se kitne ghante kam kaam hua.                                                                                                                                                                                                                                                         |
| **Overtime cost**             | Har mahine payroll se diya gaya overtime, rupaye me.                                                                                                                                                                                                                                                            |
| **Rankings** (6 lists)        | Har list me upar ke 5 employees: **Highest Attendance**, **Highest Overtime**, **Most Working Hours**, **Most Leave Taken**, **Most Absent Days**, **Highest Borrow Outstanding**. Naam par click karne se employee ki profile khulti hai.                                                                      |
| **Holidays and working days** | Is mahine ke **Working days**, aaj samet kitne baaki hain, aane wali chhuttiyan (agle chaar mahine tak) aur aane wale weekly off.                                                                                                                                                                               |
| **Joining and exits**         | **New employees this month**, **Probation ending in the next 45 days**, **Recent exits**.                                                                                                                                                                                                                       |
| **Recent employee activity**  | Employees ke record me hue sabse naye 8 badlav, kisne kiye aur kab.                                                                                                                                                                                                                                             |

## Kaam kaise karein

### Kisi aur period ka data dekhna

1. **Period** me se chunein:
    - **Current month**: chalu mahina (ye pehle se chuna hota hai)
    - **Previous month**: pichhla mahina
    - **Custom date range**: apni marzi ki tareekhein
2. **Custom date range** chunne par do naye box aate hain: **From** aur **To**. Dono tareekhein bharein. **To** ki tareekh **From** se pehle ki nahi ho sakti.
3. Tareekh chunte hi poora dashboard apne aap naye period ke hisaab se badal jata hai. Alag se koi button nahi dabana.

### Sirf ek department ya ek employee ka data dekhna

1. **Department** me department chunein. Sab dekhne ke liye **All departments**.
2. **Employee** me ek employee chunein. Sab ke liye **All employees**.
3. **Employee status** me chunein:
    - **Any status**: sab (abhi wale aur past dono)
    - **All current employees**: sirf jo abhi kaam kar rahe hain
    - **On Probation**, **Active**, **Notice Period**, **Past Employee**: sirf us status wale

Filters ek saath lagte hain. Jaise Department = Sales aur Period = Previous month chunne par sirf Sales ka pichhle mahine ka data dikhega.

### Chart ko table ki tarah dekhna

1. Chart ke upar right me **Show table** dabayein.
2. Wahi data ab numbers ki table me dikhega.
3. Wapas chart ke liye **Show chart** dabayein.

Chart ke kisi bar ya point par mouse le jane se (phone par tap karne se) uska exact number dikhta hai.

### Number ke peeche ka poora hisaab dekhna

- Kisi card par click karein, uska poora page khul jayega.
- **Deduction summary** ke neeche **Open ... payroll** se us mahine ka payroll kholein.
- Rankings ya **Joining and exits** me kisi naam par click karke employee ki profile kholein.

## Example

Aaj 12 October hai. Aarav (Company Admin) dashboard kholte hain. **Period** me **Current month** chuna hai, isliye upar likha hai ki 1 October se 31 October ka data dikh raha hai.

- **Active Employees**: 28, neeche "2 joined this month"
- **Present Today**: 24, **Absent Today**: 1, **On Leave**: 2, **Late Today**: 3
- **Payroll - September 2026**: ₹8,42,500, neeche "3% vs previous payroll" upar ke teer ke saath
- **Borrow Outstanding**: ₹61,000

Aarav ko Sales team ki pichhle mahine ki haalat dekhni hai. Wo **Period** me **Previous month** aur **Department** me **Sales** chunte hain. Ab **Attendance overview**, **Working hours** aur rankings sirf Sales ke September ke data par bante hain. **Most Absent Days** me sabse upar Rajesh ka naam hai, 4 din. Aarav naam par click karke uski profile khol lete hain.

## Dhyan rakhne wali baatein

- **"Today" wale cards hamesha aaj ke hote hain.** **Present Today**, **Absent Today**, **On Leave** aur **Late Today** par **Period** badalne ka asar nahi padta. Department, Employee aur Employee status ke filter in par lagte hain.
- **Trend wale charts hamesha pichhle 12 mahine dikhate hain**, chahe **Period** me kuch bhi chuna ho.
- **Payroll ke numbers sirf calculate ho chuke payroll se aate hain.** Draft payroll nahi gina jata. Chune hue period ka payroll na ho to sabse naya calculate hua payroll dikhaya jata hai. Isliye payroll card par mahine ka naam zaroor padhein.
- **Borrow salary nahi hai.** Diya gaya borrow / advance hamesha salary ke kharch se alag dikhaya jata hai.
- **Attendance rate ka hisaab**: aaye hue din (present, WFH, late, short hours aur paid leave; half day aadha gina jata hai) ko period ke sab darj working days se bhaag dete hain.
- **Highest Attendance** ki list me sirf wo log aate hain jinke period me kam se kam 5 working din darj hain.
- Rankings sirf ginti dikhati hain. Ye kisi ko "sabse achha" ya "sabse kharab" employee nahi batati.
- **Holidays and working days** aur **Joining and exits** hamesha chalu mahine ke hisaab se hote hain.
- Jis hisse ka data nahi hota wahan khali message aata hai, jaise "No payroll has been calculated yet". Ye koi galti nahi hai.
- Dashboard par jo dikh raha hai wo aapke role par bhi nirbhar hai. Kisi card par click karne ke baad agar page na khule to us module ki ijazat aapke role me nahi hai.

**Phone par:** cards do-do ki line me aate hain aur charts ek ke neeche ek. Filters bhi ek ke neeche ek aa jate hain. Side me scroll karne ki zaroorat nahi padti.

## Aksar pooche jane wale sawal

**Dashboard khali kyun dikh raha hai?**
Nayi company me abhi employees, attendance ya payroll nahi hote. Pehle employees add karein, attendance mark karein aur payroll calculate karein. Data aate hi charts bhar jayenge.

**Payroll card me pichhle mahine ka naam kyun hai?**
Kyunki chalu mahine ka payroll abhi calculate nahi hua. Tab tak sabse naya calculate hua payroll dikhta hai.

**Present Today me late aane wale bhi gine jate hain?**
Haan. Late, short hours, WFH aur half day wale sab **Present Today** me shamil hain. **Late Today** unhi me se late walon ki alag ginti hai.

**Kya dashboard se kuch badla ja sakta hai?**
Nahi. Dashboard sirf dikhata hai. Badlav ke liye us module me jayein.

**Filter lagane ke baad wapas sab kaise dekhein?**
**Period** me **Current month**, **Department** me **All departments**, **Employee** me **All employees** aur **Employee status** me **Any status** chun lein.

**Charts ke numbers payroll report se thode alag kyun lag rahe hain?**
Pehle dekhein ki dono jagah same mahina aur same department chuna hai ya nahi. Dashboard par filter lage hon to numbers sirf us hisse ke hote hain.
