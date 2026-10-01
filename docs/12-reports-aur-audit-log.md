# Reports aur Audit Log

Ye module aapko "poori company ka hisaab ek jagah" dikhata hai. **Reports** se aap har employee ki attendance aur borrow ka total dekh sakte hain aur file download kar sakte hain. **Payroll Reports** se har mahine ki salary ka total aur department-wise kharcha dikhta hai. **Audit Logs** mein ye record rehta hai ki system mein kisne, kab, kya badla.

## Kahan milega

| Screen              | Menu mein kahan                            |
| ------------------- | ------------------------------------------ |
| **Reports**         | Left menu mein seedha **Reports**          |
| **Payroll Reports** | **Payroll** → **Payroll Reports**          |
| **Audit Logs**      | Left menu mein sabse neeche **Audit Logs** |

## Kaun use kar sakta hai

| Screen                            | Company Admin | HR Manager | Viewer |
| --------------------------------- | ------------- | ---------- | ------ |
| **Reports** (dekhna aur download) | Haan          | Haan       | Haan   |
| **Payroll Reports**               | Haan          | Haan       | Haan   |
| **Audit Logs**                    | Haan          | Haan       | Nahi   |

Agar aapki company ne apna alag role banaya hai, to ye screens tabhi dikhengi jab us role mein **View reports**, **View payroll and salary** ya **View audit logs** tick kiya gaya ho. Role ke baare mein [Company Settings](13-company-settings.md) dekhein.

## Screen par kya dikhta hai

### Reports

- Upar do button: **Attendance report** aur **Borrow report**. Jo chahiye us par click karein.
- **Attendance report** mein mahina chunne ka box aata hai (aage-peeche ke arrow ke saath).
- Search box (**Name, ID or email**) aur department ka dropdown (**All departments**).
- Upar right mein **Download CSV** button.
- Neeche table. Ek page par 15 employees aate hain, baaki ke liye neeche page badlein.
- Sabse neeche **Payroll export** ka card.

**Attendance report ke columns**

| Column              | Matlab                                       |
| ------------------- | -------------------------------------------- |
| **Employee**        | Naam, Employee ID aur department             |
| **Working days**    | Us mahine mein employee ke kaam ke din       |
| **Present**         | Kitne din present                            |
| **Absent**          | Kitne din absent                             |
| **Half day**        | Kitne half day                               |
| **Paid leave**      | Paid chhutti ke din                          |
| **Unpaid leave**    | Bina salary wali chhutti ke din              |
| **WFH**             | Ghar se kaam ke din                          |
| **Late**            | Kitne din late aaye                          |
| **Required**        | Kitne ghante kaam karna tha (jaise 208h 00m) |
| **Worked**          | Kitne ghante kaam kiya                       |
| **Short**           | Kitne ghante kam pade                        |
| **Overtime**        | Kitne ghante zyada kaam kiya                 |
| **Attendance rate** | Attendance ka percent                        |

**Attendance rate** aise banta hai: jitne din employee aaya (paid leave bhi gini jati hai, half day aadha gina jata hai) usko ab tak nikal chuke working days se divide kiya jata hai. Is report mein sirf abhi kaam kar rahe (current) employees aate hain.

**Borrow report ke columns**

| Column              | Matlab                                         |
| ------------------- | ---------------------------------------------- |
| **Employee**        | Naam aur Employee ID                           |
| **Department**      | Department                                     |
| **Borrow records**  | Employee ke kitne borrow / advance record hain |
| **Total borrowed**  | Kul kitna diya gaya                            |
| **Total recovered** | Kul kitna wapas aa chuka                       |
| **Outstanding**     | Abhi kitna baaki hai                           |

Is report mein har wo employee aata hai jiska koi borrow ya advance record hai, **past employees bhi**. Cancel kiye gaye borrow nahi gine jaate.

### Payroll Reports

- **Month by month**: pichhle 24 payroll, sabse naya upar. Columns: **Month**, **Status**, **Employees**, **Gross salary**, **Total earnings**, **Deductions**, **Borrow given**, **Borrow recovery**, **Net payable**. Mahine ke naam par click karne se wo payroll khul jata hai.
- **Department breakdown**: ek payroll chunein aur dekhein ki us mahine ka paisa kis department mein kitna gaya. Columns: **Department**, **Employees**, **Gross salary**, **Overtime**, **Bonus and other earnings**, **Attendance and unpaid leave**, **Short hours**, **Borrow recovery**, **Other deductions**, **Borrow given**, **Net payable**. Do ya zyada department hone par sabse neeche **Total** ki line aati hai. Jis employee ka department nahi hai wo **No department** mein dikhta hai.

**Status** ka matlab:

| Status           | Matlab                                    |
| ---------------- | ----------------------------------------- |
| **Draft**        | Payroll bana hai, abhi calculate nahi hua |
| **Calculated**   | Salary calculate ho chuki hai             |
| **Admin Review** | Admin check kar raha hai                  |
| **Adjusted**     | Admin ne kuch amount badle hain           |
| **Finalized**    | Payroll final aur lock ho chuka hai       |

Katne wali rakam minus (-) ke saath dikhti hai. **Borrow given** (naya advance jo salary ke saath diya gaya) hamesha alag column mein dikhta hai, ye kamai (earning) mein nahi joda jata.

### Audit Logs

Upar filter ki line: search box (**Search descriptions**), **All areas**, **All users**, aur do date box (kab se, kab tak). Neeche table, ek page par 25 entries.

| Column            | Matlab                                                             |
| ----------------- | ------------------------------------------------------------------ |
| **What happened** | Kya hua, seedhi bhasha mein (jaise "Payroll settings updated")     |
| **When**          | Tareekh aur samay                                                  |
| **By**            | Kisne kiya. **System** likha ho to kaam apne aap hua tha           |
| **Action**        | Kaam ka prakar (jaise "Payroll: finalized")                        |
| **Employee**      | Jis employee se juda hai (naam par click karke profile khulti hai) |
| **IP address**    | Kis computer / network se kaam hua, uska number                    |

Jis entry mein purani aur nayi value dono record hui hain, uske saamne **View changes** button aata hai.

## Kaam kaise karein

### Kisi mahine ki attendance report dekhna

1. Left menu se **Reports** kholein.
2. **Attendance report** chunein (ye pehle se chuna hota hai).
3. Mahine ke box mein mahina chunein, ya arrow se pichhla / agla mahina.
4. Zaroorat ho to **All departments** se department chunein, ya search box mein naam, Employee ID ya email likhein.
5. Table mein har employee ke din aur ghante dekh lein.

### Borrow report dekhna

1. **Reports** kholein.
2. **Borrow report** par click karein.
3. Department ya naam se filter karein.
4. **Outstanding** column se pata chalega kis par kitna baaki hai.

### Report ko file mein download karna

1. Report aur filter waise set karein jaise aapko chahiye.
2. Upar right mein **Download CSV** par click karein.
3. File download ho jayegi. Ise Excel ya kisi bhi spreadsheet mein khol sakte hain.

File mein **filter se match hone wale saare employees** aate hain, sirf screen wale 15 nahi. Attendance ki file mein kuch extra column bhi hote hain: weekly off, holidays aur unmarked din. File mein ghante decimal mein likhe hote hain (jaise 7.5 ghante).

### Poore payroll ki file download karna

1. **Reports** screen par sabse neeche **Payroll export** card tak jayein.
2. **Payroll** dropdown se mahina chunein.
3. **Download payroll CSV** par click karein.

File mein har employee ki ek line hoti hai: gross salary, overtime, bonus, other earnings, attendance deduction, unpaid leave, short hours deduction, borrow recovery, other deductions, net salary, naya borrow / advance aur net payable. List mein sirf wo payroll aate hain jo calculate ho chuke hain (Draft wale nahi), aur pichhle 24 tak.

### Department-wise salary ka kharcha dekhna

1. **Payroll** → **Payroll Reports** kholein.
2. **Department breakdown** card mein dropdown se payroll chunein.
3. Table mein har department ka gross, katautiyan aur net payable dekhein.
4. File chahiye to **Export CSV** dabayein (ye button unhe dikhta hai jinke paas reports dekhne ki permission hai).

### Pata karna ki koi cheez kisne badli

1. **Audit Logs** kholein.
2. **All areas** se wo hissa chunein jahan badlav hua (jaise **Attendance** ya **Payroll**).
3. Zaroorat ho to **All users** se user chunein aur dono date box se tareekh ka range dein.
4. Yaad ho to search box mein koi shabd likhein (jaise employee ka naam ya "finalized").
5. Sahi entry milne par **View changes** par click karein.
6. Chhoti window mein teen column aayenge: **Field** (kaunsi cheez), **Before** (pehle kya tha), **After** (ab kya hai). Jo value badli hai uski purani value kati hui (strike) dikhti hai.

Agar kuch na mile to **Clear filters** se saare filter hata dein.

**All areas** ki list mein ye options hain: Employees, Attendance, Salary, Payroll, Borrow, Overtime, Short hours, Leave, Final settlement, Work shifts, Settings. **Settings** chunne par Attendance settings aur Payroll settings ke badlav aate hain. Bonus, deduction, holiday, document, role, user aur company details ke badlav bhi record hote hain, lekin inke liye list mein alag option nahi hai. Inhe dhoondhne ke liye search box, user ya date filter use karein.

### Audit Logs mein kya-kya record hota hai

- **Employees**: naya employee, details badalna, shift badalna, exit, wapas rakhna, document upload / delete
- **Attendance**: attendance lagana, badalna, hatana, attendance generate karna
- **Leave**: chhutti banana, approve / reject / cancel, balance badalna
- **Salary**: pehli salary set karna aur har revision
- **Payroll**: banana, calculate, review, adjustment, finalize, reopen, delete
- **Borrow**: naya borrow, recovery, cancel
- **Overtime, Bonus, Deduction, Short hours**: add, badlav, delete
- **Final settlement**: adjustment, finalize, paid mark karna
- **Work shifts, weekly holidays, holidays**: add, badlav, delete
- **Settings**: attendance settings, payroll settings, company details, roles aur users
- Platform owner ne aapki company mein kisi user ke roop mein sign in kiya ho, to wo bhi

## Example

**Report:** HR Meera ko September 2026 ki Production department ki attendance accounts ko bhejni hai.

1. Meera **Reports** kholti hain, **Attendance report** chuna hua hai.
2. Mahina September 2026 karti hain aur department mein Production chunti hain.
3. Table mein dikhta hai: Rahul Verma, Working days 26, Present 23, Absent 1, Half day 1, Paid leave 1, Attendance rate 94.2%.
4. **Download CSV** dabati hain aur file accounts ko bhej deti hain.

**Audit log:** Company Admin Aarav ko lagta hai ki Rahul ki 12 September ki attendance kisi ne badli hai.

1. Aarav **Audit Logs** kholte hain, area mein **Attendance** chunte hain, dono date 12 September se 15 September tak rakhte hain.
2. List mein Rahul ki entry milti hai, **By** mein Meera Joshi ka naam hai.
3. **View changes** kholne par dikhta hai: Status, Before "absent", After "present".

**Payroll Reports:** September payroll mein Production department ka Gross salary ₹4,20,000, Borrow recovery -₹12,000 aur Net payable ₹3,95,500 dikhta hai. Usi line mein Borrow given +₹10,000 alag column mein hai, yani ek employee ko is mahine salary ke saath naya advance diya gaya.

## Dhyan rakhne wali baatein

- Reports sirf dekhne ke liye hain. Yahan se koi data badalta nahi.
- Attendance report mein sirf current employees aate hain. Past employee ka record **Employees** → **Past Employees** mein uski profile se dekhein.
- Borrow report mein mahine ka filter nahi hai, ye ab tak ka poora total dikhata hai.
- **Download CSV** hamesha aapke lagaye filter ke hisaab se file banata hai. Filter galat hoga to file bhi adhoori hogi.
- Payroll export mein Draft payroll nahi aata. Pehle payroll calculate karein.
- Audit log **permanent** hai. Koi bhi, Company Admin bhi, isme entry badal ya delete nahi kar sakta.
- Audit log sirf aapki apni company ka record dikhata hai.
- Phone par table ki har line ek card ban jati hai. Audit Logs mein phone par **IP address** nahi dikhta, baaki sab dikhta hai.

## Aksar pooche jane wale sawal

**Report mein ek employee nahi dikh raha, kyun?**
Search box ya department filter check karein. Attendance report mein past employees nahi aate. Borrow report mein sirf wo aate hain jinka koi borrow record hai.

**Download ki hui file mein screen se zyada employees kyun hain?**
Screen par ek baar mein 15 dikhte hain, file mein filter se match hone wale saare aate hain.

**Attendance rate 100% se kam kyun hai jabki employee roz aaya?**
Half day aadha gina jata hai aur absent ya unpaid leave ke din nahi gine jaate. Present, Half day aur Absent ke column dekh kar samajh aa jayega.

**Net payable aur Total earnings mein fark kyun hai?**
Total earnings mein se katautiyan aur borrow recovery ghatti hain, aur naya borrow (Borrow given) judta hai. Poora hisaab [Payroll aur Salary Slip](10-payroll-aur-salary-slip.md) mein samjhaya gaya hai.

**Audit log mein "System" kya hai?**
Jo kaam kisi login kiye hue user ne nahi, system ne apne aap kiya, uske saamne **System** likha aata hai.

**Kya main audit log ki galat entry hata sakta hoon?**
Nahi. Galti sudharne ke liye sahi screen par jaakar data theek karein. Us sudhaar ki bhi nayi entry ban jayegi.
