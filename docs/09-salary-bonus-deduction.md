# Salary, Bonus aur Deduction

Ye module batata hai har employee ki mahine ki salary kya hai, wo kin hisson (components) se bani hai, aur usme kab-kab badlav hua.
Saath hi yahan se aap ek baar ke bonus, doosri kamaai (other earning) aur ek baar ki katauti (deduction) darj karte hain, jo agli salary me apne aap jud ya kat jaati hai.

## Kahan milega

| Kaam                                         | Menu                                                                                        |
| -------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Har employee ki abhi ki salary               | **Payroll → Salary Structure**                                                              |
| Ek employee ki salary aur uski poori history | **Salary Structure** me employee par click, ya employee profile par **Salary** button       |
| Poori company ke salary badlav               | **Payroll → Salary Revisions** (yahi page **Employees → Salary History** se bhi khulta hai) |
| Bonus aur doosri kamaai                      | **Employee Finance → Bonuses**                                                              |
| Ek baar ki katauti                           | **Employee Finance → Deductions**                                                           |

Naya employee add karte waqt **Add Employee** form ke **Salary** section me bhi pehli salary bhari ja sakti hai.

## Kaun use kar sakta hai

| Role              | Salary                          | Bonus / Deduction         |
| ----------------- | ------------------------------- | ------------------------- |
| **Company Admin** | Dekhna, set karna, revise karna | Dekhna, add, edit, delete |
| **HR Manager**    | Dekhna, set karna, revise karna | Dekhna, add, edit, delete |
| **Viewer**        | Sirf dekhna                     | Sirf dekhna               |

Apne banaye role ke liye **Settings → Roles & Permissions** me ye permission dekhiye: **View payroll and salary**, **Run payroll and revise salary**, **View borrow, overtime, bonuses and deductions**, **Manage borrow, overtime, bonuses and deductions**.

## Screen par kya dikhta hai

### Salary Structure page

Sirf abhi kaam kar rahe (current) employees ki list:

| Column                   | Matlab                                                                                                      |
| ------------------------ | ----------------------------------------------------------------------------------------------------------- |
| **Employee**             | Naam, ID aur designation. Click karne par us employee ka salary page khulta hai                             |
| **Department**           | Department                                                                                                  |
| **Components**           | Salary ke kamaai wale hisson ke naam. Har mahine ki tay katauti ho to niche "recurring deductions" ki rakam |
| **In effect from**       | Abhi ki salary kis tareekh se laagu hai                                                                     |
| **Revisions**            | Ab tak kitni baar salary set / badli gayi                                                                   |
| **Gross monthly salary** | Mahine ki kul salary. Salary set na ho to **No salary set** likha aata hai                                  |

Upar search box (**Name, ID or email**) aur **All departments** filter hai. Har row me **View salary** button hota hai; jiski salary abhi set nahi hai uske liye **Set salary**.

### Ek employee ka Salary page

- **Current salary** : **Earnings** (jaise Basic, HRA, Other Allowance), unka jod **Gross monthly salary**, phir agar hon to **Recurring deductions** aur **After recurring deductions**.
- Agar aage ki tareekh ka koi badlav pehle se darj hai to neeche neeli patti me dikhta hai ki nayi salary kis tareekh se laagu hogi.
- **Revision history** : har badlav, naya sabse upar. Jo abhi laagu hai us par **Current**, jo aage laagu hoga us par **Upcoming** likha hota hai. Har entry me purani salary → nayi salary, kitna badha/ghata (rakam aur %), **Reason**, aur kisne kab darj kiya. **Components** par click karke **New** aur **Previous** hisse dekh sakte hain.

### Salary Revisions page

Poori company ke saare salary badlav ek jagah: **Employee**, **Effective date**, **Previous salary**, **New salary**, **Change**, **Reason**, **Changed by**. Pehli salary ke liye **Change** me **Initial salary** likha hota hai. Filter: **Employee**, **Effective from**, **Effective to**.

### Bonuses aur Deductions page

Dono page ek jaise hain: upar mahina badalne ka control, search box, us mahine ka **Total** card, aur neeche entry ki list. Jo entry kisi finalize ho chuke payroll me ja chuki hai us par taala aur **In a finalized payroll** likha hota hai; use edit ya delete nahi kar sakte.

## Kaam kaise karein

### 1. Pehli baar salary set karna

1. **Payroll → Salary Structure** kholiye. Jis employee par **No salary set** hai uske saamne **Set salary** dabaiye.
2. Employee ke salary page par upar **Set salary** dabaiye.
3. **Effective date** : salary kis tareekh se laagu hogi. Pehli salary ke liye ye apne aap joining date hoti hai. Joining date se pehle ki tareekh nahi chalti.
4. **Reason** : pehli baar ke liye "Initial salary" pehle se bhara aata hai.
5. **Salary components** me har hissa bhariye. Shuru me teen row milti hain: Basic, HRA, Other Allowance.
    - **Component** : hisse ka naam.
    - **Type** : **Earning** (kamaai) ya **Deduction** (har mahine ki tay katauti).
    - **Monthly amount** : mahine ki rakam.
    - Aur row chahiye to **Add earning** ya **Add recurring deduction** dabaiye. Row hatane ke liye dustbin ka nishan.
6. Neeche **Gross monthly salary** aur **Recurring deductions** ka jod saath-saath dikhta rehta hai. Check kar lijiye.
7. **Save revision** dabaiye.

### 2. Salary badalna (Revise salary)

Salary kabhi "edit" nahi hoti. Har badlav ek naya revision hota hai jisme nayi salary aur wo tareekh hoti hai jab se wo laagu hai. Purani salary history me waisi hi rehti hai.

1. Employee ka salary page kholiye aur **Revise salary** dabaiye.
2. **Effective date** chuniye. Aaj ki, pichhli ya aage ki tareekh, teeno chalti hain.
3. **Reason** likhiye, jaise "Annual increment" ya "Promotion".
4. Components me abhi ki salary pehle se bhari aati hai. Sirf wo rakam badliye jo badalni hai, ya naya hissa jodiye / hataiye.
5. Chahein to **Notes** likhiye, phir **Save revision** dabaiye.

Kya hota hai:

- **Aage ki tareekh** ka revision **Upcoming** dikhta hai aur us din se apne aap **Current** ban jaata hai.
- **Mahine ke beech** ki tareekh ho to us mahine ke payroll me salary dino ke hisaab se bat-ti hai: kuch din purani salary, baaki din nayi salary.
- **Pichhle mahino ke payroll** jo ban chuke hain, wo purani salary par hi rehte hain.

### 3. Har mahine ki tay katauti (Recurring deduction)

Jo katauti har mahine ek hi rakam ki hoti hai (jaise PF, professional tax, canteen), use salary ka hissa banaiye:

1. Employee ke salary page par **Revise salary** dabaiye.
2. **Add recurring deduction** dabaiye, naam aur **Monthly amount** bhariye.
3. **Save revision** dabaiye.

Ye rakam har mahine payroll me **Other Deductions** ke andar apne naam se dikhti hai.

### 4. Bonus ya doosri kamaai jodna

1. **Employee Finance → Bonuses** kholiye aur **Add bonus or earning** dabaiye.
2. **Employee** chuniye.
3. **Type** chuniye:
    - **Bonus** : salary ke upar inaam, jaise festival bonus ya performance bonus.
    - **Other earning** : koi aur ek baar ka payment, jaise reimbursement ya arrears.
4. **Title** likhiye (jaise "Festival bonus"). Yahi naam pay sheet aur salary slip par dikhega.
5. **Amount** aur **Date** bhariye. **Reason** likhna achha rehta hai.
6. **Add bonus or earning** dabaiye.

Ye rakam us agle payroll me apne aap jud jaati hai jiske period me ye tareekh aati hai, aur wahan apni alag line me dikhti hai.

### 5. Ek baar ki katauti (Deduction) jodna

1. **Employee Finance → Deductions** kholiye aur **Add deduction** dabaiye.
2. **Employee** chuniye.
3. **What is it for?** me likhiye katauti kis cheez ki hai (jaise "Uniform", "Damaged equipment").
4. **Amount**, **Date** aur chahein to **Reason** bhariye.
5. **Add deduction** dabaiye.

Ye rakam agle payroll me **Other Deductions** ke andar apne naam se kat jaati hai.

### 6. Bonus / deduction badalna ya hatana

1. List me entry ke saamne pencil ka nishan dabakar badliye, phir **Save changes**.
2. Hatane ke liye dustbin ka nishan dabaiye aur confirm kijiye. Hatayi gayi entry salary me nahi judegi / nahi kategi.

Ye tabhi tak ho sakta hai jab tak entry kisi finalize hue payroll me nahi gayi.

## Example

**Salary set karna:** Deepa Nair ki pehli salary, 1 April 2026 se:

| Component                | Type      | Monthly amount |
| ------------------------ | --------- | -------------- |
| Basic                    | Earning   | ₹14,000        |
| HRA                      | Earning   | ₹7,000         |
| Other Allowance          | Earning   | ₹3,000         |
| **Gross monthly salary** |           | **₹24,000**    |
| Professional Tax         | Deduction | ₹200           |

**Mahine ke beech increment:** 16 September 2026 se Deepa ki salary ₹27,000 kar di gayi (**Revise salary**, Reason "Annual increment"). September me 30 din hain:

- 1 se 15 September (15 din) purani salary ₹24,000 par → 24,000 × 15 / 30 = ₹12,000
- 16 se 30 September (15 din) nayi salary ₹27,000 par → 27,000 × 15 / 30 = ₹13,500
- September ki gross salary = ₹12,000 + ₹13,500 = **₹25,500**

October se poori ₹27,000 lagegi. August ka payroll, jo pehle ban chuka hai, ₹24,000 par hi rahega. History me dono entry dikhengi: 1 April 2026 (Initial salary ₹24,000) aur 16 September 2026 (₹24,000 → ₹27,000, +₹3,000, +12.5%).

**Bonus aur deduction:** 20 October 2026 ko Deepa ke liye **Festival bonus** ₹2,000 aur 22 October ko **Uniform** ki katauti ₹500 darj ki. October ke payroll me ₹2,000 **Bonus** me judega aur ₹500 **Other Deductions** me katega.

## Dhyan rakhne wali baatein

- **Purani salary kabhi badalti ya hat-ti nahi.** Revision history me kuch bhi edit ya delete nahi hota. Galat revision ban gaya ho to sahi rakam ke saath ek aur revision banaiye (alag effective date ke saath).
- **Ek employee ke liye ek tareekh par ek hi revision** ho sakta hai. Usi tareekh par doosra banane par system rok dega; doosri tareekh chuniye.
- **Effective date joining date se pehle nahi ho sakti.**
- **Kam se kam ek Earning zaroori hai** jiski rakam zero se zyada ho. Jis row me rakam khaali ya zero hai wo save nahi hoti.
- **Salary set nahi hai to payroll us employee ko kuch nahi deta.** Pay sheet par chetavni aati hai "No salary is set for this period". Payroll chalane se pehle **Salary Structure** me **No salary set** wale employees dekh lijiye.
- **Salary badalne ke baad** agar us mahine ka payroll pehle se calculate hua pada hai (finalize nahi), to payroll page par **Recalculate** dabaiye. Finalize ho chuke payroll par koi asar nahi padta.
- **Bonus / deduction kis payroll me jaayega:** jis payroll ke period me uski tareekh aati hai. Agar us mahine ka payroll pehle hi finalize ho chuka hai, to entry agle payroll me chali jaati hai.
- **Finalize ke baad entry lock ho jaati hai.** **In a finalized payroll** likhi entry ko badalne ke liye pehle wo payroll reopen karna padta hai. Dekhiye [Payroll aur Salary Slip](10-payroll-aur-salary-slip.md).
- **Har mahine wali katauti Deductions page par mat daliye.** Wahan sirf ek baar ki katauti aati hai. Har mahine wali katauti salary ke **recurring deduction** me daliye.
- **Borrow ki kisht yahan nahi aati.** Uske liye [Borrow / Advance](08-borrow-advance.md) dekhiye. Overtime aur short hours ke liye [Short Hours aur Overtime](07-short-hours-aur-overtime.md).
- **Phone par:** list card ke roop me dikhti hai. Salary component bharte waqt naam, type aur rakam ek ke neeche ek aate hain.

## Aksar pooche jane wale sawal

**Galti se galat salary save ho gayi. Kaise sudharein?**
Revision delete nahi hota. **Revise salary** se sahi rakam ka naya revision banaiye. Agar galat revision ki tareekh se hi sahi salary chahiye, to agle din ki tareekh ka revision banaiye aur farq us mahine ke payroll me adjustment se theek kar lijiye.

**Agle mahine se increment dena hai. Abhi darj kar sakte hain?**
Haan. **Effective date** me agle mahine ki tareekh daliye. Tab tak wo **Upcoming** dikhega aur us din se apne aap laagu ho jaayega.

**Salary Revisions aur Salary History me kya farq hai?**
Koi farq nahi. Dono menu ek hi page kholte hain.

**Bonus sabko ek saath de sakte hain?**
Nahi, har employee ke liye alag entry banani hoti hai.

**Jo employee company chhod chuka hai uski salary kahan dikhegi?**
**Salary Structure** me sirf current employees aate hain. Chhod chuke employee ki profile (**Employees → Past Employees**) par **Salary** button se uski poori history dekh sakte hain.

**Bonus add kiya par payroll me nahi dikh raha?**
Payroll page par **Recalculate** dabaiye. Ye bhi dekhiye ki bonus ki **Date** us payroll ke period ke andar ya usse pehle ki hai.
