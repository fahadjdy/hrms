# Employees

Ye module company ke saare employees ka record rakhta hai: unki personal jaankari, department, designation, kaam ka samay, salary, documents aur poori history. Attendance, payroll, leave aur borrow sab isi record se judte hain, isliye sabse pehle employee yahin add karna hota hai.

Employee sirf ek HR record hai. Uska koi login nahi hota.

## Kahan milega

Menu me **Employees** par click karein. Andar ye pages hain:

| Page                 | Kaam                                                                                                                 |
| -------------------- | -------------------------------------------------------------------------------------------------------------------- |
| **Active Employees** | Jo abhi company me kaam kar rahe hain unki list                                                                      |
| **Past Employees**   | Jo company chhod chuke hain. Dekhein: [Employee Exit aur Final Settlement](11-employee-exit-aur-final-settlement.md) |
| **Add Employee**     | Naya employee jodna                                                                                                  |
| **Departments**      | Departments banana aur badalna                                                                                       |
| **Designations**     | Designations (job titles) banana aur badalna                                                                         |
| **Salary History**   | Salary me hue saare badlav. Dekhein: [Salary, Bonus, Deduction](09-salary-bonus-deduction.md)                        |
| **Documents**        | Employees ke documents upload aur download karna                                                                     |

## Kaun use kar sakta hai

| Role              | Kya kar sakta hai                                                                                                             |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **Company Admin** | Sab kuch dekhna, add, edit, documents, departments, designations, employee ko exit karna                                      |
| **HR Manager**    | Company Admin jaisa hi, is module me sab kuch                                                                                 |
| **Viewer**        | Sirf dekhna: list, profile, departments, designations aur documents download karna. Add / edit / delete ke button nahi dikhte |

Profile par salary, borrow, leave aur attendance ke hisse tabhi dikhte hain jab aapke role me un modules ko dekhne ki ijazat ho.

## Screen par kya dikhta hai

### Active Employees

- Upar right me **Add employee** button.
- Search box (**Name, ID or email**) aur chaar filters: **All departments**, **All designations**, **All statuses**, **All employment types**.
- Table ke columns: **Employee** (photo, naam, Employee ID, designation), **Department**, **Type**, **Joined**, **Phone**, **Status**.
- Har row ke aakhir me do chhote button: calendar wala (us employee ka attendance calendar) aur pencil wala (edit).
- Ek page par 15 employees aate hain. Neeche se agla page khulta hai.

Status ka matlab:

| Status            | Matlab                                                                                     |
| ----------------- | ------------------------------------------------------------------------------------------ |
| **On Probation**  | Abhi probation chal raha hai                                                               |
| **Active**        | Pakka employee, kaam kar raha hai                                                          |
| **Notice Period** | Chhodne wala hai, notice chal raha hai. Abhi bhi kaam kar raha hai aur payroll me aata hai |
| **Past Employee** | Company chhod chuka hai. Ye **Past Employees** page par dikhta hai                         |

Employment type ke options: **Full Time**, **Part Time**, **Contract**, **Intern**, **Temporary**.

### Employee ki profile

List me naam par click karne se profile khulti hai. Sabse upar photo, naam, status, Employee ID, designation aur department. Uske saamne ye button (role ke hisaab se):

- **Attendance calendar**: us employee ka mahine ka attendance
- **Salary**: salary ka structure aur history
- **New borrow**: is employee ko naya borrow / advance dena
- **Edit**: jaankari badalna
- **Leave company**: employee ko exit karna

Neeche ye hisse hain:

| Hissa                     | Kya dikhata hai                                                                                                                                                                                                                                |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Details**               | Employee ID, department, designation, employment type, reporting manager, joining date, probation ki aakhri tareekh, gender, date of birth, phone, email, address aur notes                                                                    |
| **Attendance - (period)** | Chalu payroll period ka saar: working days, present, absent, half day, paid leave, unpaid leave, late, not marked, required hours, worked hours, short hours, overtime aur **Attendance rate**. **Open calendar** se poora calendar khulta hai |
| **Borrow / advance**      | **Total borrowed**, **Total recovered**, **Total outstanding** aur har borrow alag line me: reference number, "Existing at joining" ya "New borrow", tareekh, mahine ki kist, amount, baaki rakam aur status                                   |
| **Recent activity**       | Is employee ke record me hue sabse naye badlav, kisne kiye aur kab                                                                                                                                                                             |
| **Work timing**           | Kaun si shift lagu hai, samay, roz ke zaroori ghante, aur ye shift kahan se aayi                                                                                                                                                               |
| **Current salary**        | Mahine ki gross salary, kab se lagu hai, kitni baar badli, aur har component                                                                                                                                                                   |
| **Leave balance (saal)**  | Har leave type me kitni chhutti li, kitni pending hai aur kitni baaki hai                                                                                                                                                                      |

**Work timing** me shift ke neeche ek label hota hai jo batata hai ki shift kahan se aayi:

| Label                            | Matlab                                                           |
| -------------------------------- | ---------------------------------------------------------------- |
| **Set for this employee**        | Is employee ke liye alag se shift di gayi hai                    |
| **Gender-based company default** | Company ne gender ke hisaab se shift rakhi hai aur wahi lagu hai |
| **Company default shift**        | Company ki aam shift lagu hai                                    |

Shifts ke baare me poori jaankari: [Work Shifts aur Holidays](04-work-shifts-aur-holidays.md)

## Kaam kaise karein

### Employee dhoondhna

1. **Employees** me **Active Employees** kholein.
2. Search box me naam, Employee ID ya email likhein. List likhte hi chhant jati hai.
3. Zaroorat ho to department, designation, status ya employment type ka filter lagayein.
4. Kuch na mile to **Clear filters** dabakar sab filters hata dein.

### Naya employee add karna

**Employees** me **Add Employee** kholein (ya list ke upar **Add employee** button). Form me paanch hisse hain. Jin box par laal tara (*) hai wo bharna zaroori hai.

**1. Personal information**

| Field                                                          | Kya bharna hai                                                                                                                                 |
| -------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| **Profile photo**                                              | Chahein to photo lagayein. JPG, PNG ya WebP, 2 MB tak                                                                                          |
| **Employee ID** *                                              | System agla number khud sujhata hai, jaise EMP-0031. Aap badal sakte hain, par company me do employees ka ID same nahi ho sakta                |
| **First name** *                                               | Naam                                                                                                                                           |
| **Last name**                                                  | Surname                                                                                                                                        |
| **Date of birth**                                              | Janm ki tareekh (aaj se pehle ki)                                                                                                              |
| **Gender**                                                     | **Male**, **Female**, **Other** ya khali (**Not specified**). Ye sirf tab kaam aata hai jab company ne gender ke hisaab se alag shift rakhi ho |
| **Phone**, **Email**                                           | Sampark ke liye                                                                                                                                |
| **Address**, **City**, **State**, **Country**, **Postal code** | Pata                                                                                                                                           |

**2. Employment**

| Field                 | Kya bharna hai                                                                                                 |
| --------------------- | -------------------------------------------------------------------------------------------------------------- |
| **Joining date** *    | Join karne ki tareekh. Attendance aur salary isi din se shuru hoti hai                                         |
| **Department**        | List me se chunein. Khali chhodne par **No department**                                                        |
| **Designation**       | List me se chunein                                                                                             |
| **Employment type** * | **Full Time**, **Part Time**, **Contract**, **Intern** ya **Temporary**                                        |
| **Reporting manager** | Ye employee kise report karta hai. List me sirf abhi ke employees aate hain                                    |
| **Employee status** * | **On Probation**, **Active** ya **Notice Period**                                                              |
| **Probation ends on** | Probation ki aakhri tareekh. Probation nahi hai to khali chhod dein. Ye joining date se pehle ki nahi ho sakti |
| **Notes**             | Koi bhi tippani                                                                                                |

**3. Work timing**

- **Employee-specific work shift**: sirf tab chunein jab is employee ka samay baaki sab se alag ho.
- Khali chhodne par (**Use the default shift (gender or company)**) company ki gender wali shift lagegi agar bani hai, warna company ki aam shift.
- Ise baad me profile se badla ja sakta hai.

**4. Salary**

- Teen line pehle se bani hoti hain: **Basic**, **HRA**, **Other Allowance**.
- Har line me **Component** (naam), **Type** (**Earning** = kamai, **Deduction** = har mahine ki katauti) aur **Monthly amount** bharein.
- Nayi line ke liye **Add component**, hatane ke liye line ke saamne dustbin wala button.
- Neeche **Gross salary per month** apne aap jud kar dikhta hai. Deduction wali lines ka jod **Recurring deductions** me dikhta hai.
- Jis line me amount nahi bhara wo chhod di jati hai. Salary abhi nahi pata to sab khali chhod dein aur baad me profile se set karein.
- Ye salary joining date se lagu hoti hai.

**5. Existing borrow / advance**

Ye hissa us employee ke liye hai jo join karte samay hi company ka paisa udhaar liye hue hai (jaise purana advance).

1. **Has an existing borrow** ka switch on karein.
2. Ye fields bharein:

| Field                              | Kya bharna hai                                                                                                |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| **Original borrow amount** *       | Shuru me kitna liya tha                                                                                       |
| **Outstanding balance at joining** | Abhi kitna baaki hai. Ab tak kuch nahi chukaya to khali chhod dein. Ye original amount se zyada nahi ho sakta |
| **Original borrow date**           | Kab liya tha. Khali chhodne par joining date maani jati hai                                                   |
| **Monthly deduction**              | Har mahine salary se kitna katega                                                                             |
| **Number of installments**         | Kitni kiston me. Agar **Monthly deduction** khali hai to system isse kist khud nikal leta hai                 |
| **Start deducting from**           | Kis mahine se katna shuru ho. Khali chhodne par borrow ki tareekh ke agle mahine se                           |
| **Reason**                         | Kis liye liya tha                                                                                             |
| **Source / reference**             | Jaise pichhli company ka naam ya agreement number                                                             |
| **Notes**                          | Koi tippani                                                                                                   |

Sab bharne ke baad neeche **Add employee** dabayein. Employee ban jata hai aur uski profile khul jati hai. Kuch galat ho to us field ke neeche laal message aata hai aur neeche likha aata hai "Some fields need attention."

### Employee ki jaankari badalna

1. Profile par **Edit** dabayein (ya list me pencil wala button).
2. **Personal information** aur **Employment** me jo badalna hai badlein. Nayi photo lagane par purani hat jati hai.
3. **Save changes** dabayein.

Edit form me salary aur work timing nahi hote. Wo profile se badle jate hain taaki unki history bani rahe.

### Work timing (shift) badalna

1. Profile par **Work timing** hisse me **Change** dabayein.
2. **Work shift** me nayi shift chunein. Alag shift hatani ho to **Use the default shift (gender or company)** chunein.
3. **Effective from** me tareekh daalein, kab se lagu ho.
4. **Save work timing** dabayein.

Us tareekh se pehle ke dino par purana samay hi rehta hai.

### Salary set karna ya badalna

Profile par **Current salary** hisse me **Set salary** (ya **History and revisions**) dabayein, ya upar **Salary** button. Poora tareeka: [Salary, Bonus, Deduction](09-salary-bonus-deduction.md)

### Naya borrow dena

Profile par **New borrow** dabayein. Employee pehle se chuna hua milega. Poora tareeka: [Borrow / Advance](08-borrow-advance.md)

### Document upload karna

1. **Employees** me **Documents** kholein.
2. **Upload document** dabayein.
3. **Employee** chunein.
4. **File** chunein: PDF, photo (JPG, PNG, WebP), Word ya Excel, 10 MB tak.
5. **Title** likhein, jaise "Offer letter". File chunte hi uska naam yahan apne aap aa jata hai, aap badal sakte hain.
6. **Document type** chunein: **ID Proof**, **Address Proof**, **Offer Letter**, **Contract**, **Certificate**, **Bank Details** ya **Other**.
7. **Upload document** dabayein.

### Document dekhna ya download karna

1. **Documents** page par search box me title ya employee ka naam likhein, ya **All employees** / **All document types** se chhantein.
2. Document ke naam par ya download wale button par click karein. File download ho jati hai.

### Document delete karna

1. Document ki row me dustbin wala button dabayein.
2. **Delete document** se pakka karein.

### Department banana, badalna, hatana

1. **Employees** me **Departments** kholein.
2. **Add department** dabayein.
3. **Name** (zaroori) aur **Description** likhein. **Active** switch on rehne dein.
4. **Add department** dabayein.

Badalne ke liye row me pencil wala button, phir **Save changes**. Hatane ke liye dustbin wala button, phir **Delete department**.

List me har department ke saamne **Employees** me dikhta hai ki usme kitne log hain, aur **Status** me **Active** ya **Inactive**.

### Designation banana, badalna, hatana

**Employees** me **Designations** kholein. Tareeka bilkul departments jaisa hai: **Add designation**, **Name**, **Description**, **Active**. Designation employee ki profile aur salary slip par dikhta hai.

## Example

Acme Traders me 1 November 2026 ko Sana Qureshi "Senior Accountant" ban kar Finance department me join kar rahi hain. Salary ₹45,000 mahina hai, aur unhone join karne se pehle ₹30,000 ka advance liya tha jisme se ₹24,000 baaki hai.

Meera (HR Manager) **Add Employee** kholti hain:

- **Employee ID**: EMP-0031 (system ne sujhaya)
- **First name**: Sana, **Last name**: Qureshi, **Gender**: Female
- **Joining date**: 01 Nov 2026, **Department**: Finance, **Designation**: Senior Accountant
- **Employment type**: Full Time, **Employee status**: On Probation, **Probation ends on**: 30 Apr 2027
- **Work timing**: khali chhoda, company ki default shift lagegi
- **Salary**: Basic ₹27,000, HRA ₹13,500, Other Allowance ₹4,500. **Gross salary per month** ₹45,000 dikhta hai
- **Has an existing borrow** on: **Original borrow amount** ₹30,000, **Outstanding balance at joining** ₹24,000, **Monthly deduction** ₹4,000, **Start deducting from** December 2026

**Add employee** dabate hi Sana ki profile khulti hai. **Current salary** me ₹45,000 aur **Borrow / advance** me ₹24,000 outstanding dikhta hai, "Existing at joining" ke label ke saath. December ke payroll se har mahine ₹4,000 katna shuru hoga.

## Dhyan rakhne wali baatein

- **Employee kabhi delete nahi hota.** Delete ka koi button nahi hai. Koi chhod kar jaye to profile par **Leave company** use karein. Wo **Past Employees** me chala jata hai aur uski poori history bani rehti hai.
- **Employee ID company me unique hona chahiye.** Same ID dobara dene par form save nahi hoga.
- **Joining date soch samajh kar bharein.** Attendance aur salary isi tareekh se gini jati hai.
- **Salary edit form se nahi badalti.** Har salary badlav ek nayi entry banta hai aur purani entry history me rehti hai.
- **Salary set na ho to payroll us employee ko kuch nahi deta.** Profile par likha aata hai "No salary is set yet."
- **Salary me kam se kam ek Earning honi chahiye** jiski amount zero se zyada ho. Sirf Deduction wali lines se salary nahi banti.
- **Employee khud apna reporting manager nahi ho sakta.**
- **Existing borrow alag record banta hai.** Wo salary ki kamai me nahi judta, aur payroll se kist ke hisaab se wapas liya jata hai.
- **Department ya designation tab tak delete nahi hota jab tak usme employees hain.** Pehle employees ko doosre department / designation me daalein, ya use **Inactive** kar dein.
- **Inactive department / designation** purane employees par bana rehta hai, lekin naye employee ke form me chunne ke liye nahi aata.
- **Document delete karne par wapas nahi aata.** Delete se pehle soch lein.
- **Past employee ka status edit form se nahi badalta.** Use wapas lana ho to profile par **Reinstate employee** use karein.
- Employee add, edit, shift badalna, document upload / delete, sab **Audit Logs** me darj hota hai.

**Phone par:** employees ki table cards me badal jati hai, har employee ek card. **Phone** column phone par chhupa rehta hai. Form ke fields ek ke neeche ek aa jate hain, aur salary ki har line ek alag dabba ban jati hai.

## Aksar pooche jane wale sawal

**Galti se employee add ho gaya, use kaise hataun?**
Delete nahi hota. Agar jaankari galat hai to **Edit** se sahi kar dein. Agar wo vyakti company me hai hi nahi to **Leave company** se use past employee bana dein.

**Add karte samay salary nahi pata thi, ab kaise daalun?**
Profile par **Current salary** me **Set salary** dabayein.

**Ek employee ka samay baaki sab se alag hai, kya karun?**
Profile par **Work timing** me **Change** dabakar uske liye alag shift chunein. Shift pehle se bani honi chahiye.

**Employee ki profile par documents kahan dikhte hain?**
Documents alag page par hain. **Employees** me **Documents** kholein aur **All employees** wale filter me us employee ko chunein.

**Notice Period wale employee ko payroll me salary milegi?**
Haan. Jab tak aap **Leave company** nahi karte, wo abhi ka employee hi gina jata hai.

**List me koi employee nahi dikh raha jo pehle tha?**
Ya to koi filter ya search laga hai (use hata kar dekhein), ya wo exit ho chuka hai. **Past Employees** me dekhein.
