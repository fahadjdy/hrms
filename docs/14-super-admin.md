# Super Admin (Platform Owner)

Ye guide sirf platform ke maalik, yani **Super Admin**, ke liye hai. Super Admin ka kaam companies ko jodna, unhe chalu ya band karna, aur zaroorat padne par kisi company ke user ke roop mein andar jaakar madad karna hai. Employees, attendance aur payroll ka roz ka kaam har company ke apne Admin aur HR karte hain.

## Kahan milega

Super Admin ke login par left menu mein sirf do cheezein hoti hain:

| Menu item             | Kya hota hai                                                  |
| --------------------- | ------------------------------------------------------------- |
| **Companies**         | Saari companies ki list. Login ke baad yahi screen khulti hai |
| **Platform Settings** | Platform ka naam aur nayi companies ke default                |

## Kaun use kar sakta hai

Sirf **Super Admin**. Kisi company ka Admin ya HR ye screens nahi khol sakta, aur ek company ka user doosri company ka data kabhi nahi dekh sakta.

Super Admin ko company ke andar ka data (employees, attendance, payroll) seedha nahi dikhta. Use dekhne ke liye **Sign in as** ka use karna hota hai (neeche samjhaya gaya hai).

## Screen par kya dikhta hai

### Companies

Upar chaar card:

| Card                 | Matlab                                                         |
| -------------------- | -------------------------------------------------------------- |
| **Companies**        | Kul kitni companies hain                                       |
| **Active companies** | Kitni chalu hain. Neeche likha hota hai kitni deactivated hain |
| **Active employees** | Saari companies ke current employees ka total                  |
| **Company users**    | Saari companies ke Admin aur HR logins ka total                |

Uske neeche search box (**Company name or email**) aur status ka dropdown (**Active and inactive**, **Active**, **Inactive**). Upar right mein **Add company** button.

Table ke columns:

| Column               | Matlab                                                                     |
| -------------------- | -------------------------------------------------------------------------- |
| **Company**          | Logo, naam (click karne par company ka page khulta hai), aur sheher / desh |
| **Email**            | Company ka email                                                           |
| **Active employees** | Current employees, saath mein kul kitne record hain (jaise "42 of 50")     |
| **Users**            | Kitne login hain                                                           |
| **Created**          | Company kab jodi gayi                                                      |
| **Status**           | **Active** ya **Inactive**                                                 |

Har line ke aakhir mein do chhote button: andar jaate teer (arrow) wala button (company ke admin ke roop mein sign in, sirf active company par dikhta hai) aur pencil (edit). Button par mouse le jaane se uska kaam likha aata hai, jaise "Sign in as the admin of Shree Textiles". Ek page par 15 companies aati hain.

### Company ka page

Company ke naam par click karne se khulta hai.

- Upar: status ka badge, **Edit** button aur **Sign in as company admin** button.
- Chaar card: **Active employees**, **Past employees**, **Users**, **Latest payroll** (sabse naye payroll ka net payable, us company ki apni currency mein, saath mein mahina aur status).
- **Company details**: email, phone, GST / tax number, address, currency, timezone, banne ki tareekh.
- **Deactivate company** ya **Activate company** ka card.
- **Users**: us company ke saare logins (**User**, **Email**, **Role**, **Status**). Har active user ke saamne **Sign in as {naam}** button.

### Platform Settings

| Hissa                          | Fields                                                                                                    |
| ------------------------------ | --------------------------------------------------------------------------------------------------------- |
| **Platform**                   | **Platform name** (zaroori), **Support email** (companies platform owner se yahan sampark kar sakti hain) |
| **Defaults for new companies** | **Currency**, **Timezone**, **Date format**                                                               |

Neeche **Save platform settings** button.

## Kaam kaise karein

### Nayi company jodna

1. **Companies** screen par **Add company** dabayein.
2. **Company** hisse mein **Company name** (zaroori), aur chahein to **Legal name**, **Email**, **Phone**, **GST / tax number** aur **Logo** (JPG, PNG ya WebP, 2 MB tak) bharein.
3. **Address** hisse mein address, city, state, country, postal code bharein.
4. **Regional settings** mein **Currency**, **Timezone** aur **Date format** check karein. Ye Platform Settings ke default se pehle se bhare aate hain.
5. **First Company Admin** hisse mein us vyakti ka **Name**, **Email** (isi se wo login karega) aur **Password** bharein.
6. **Create company** dabayein.
7. Company ka page khulte hi **Share login details** box aata hai. Isme **Login URL**, **Email** aur **Password** hain, har ek ke saath copy button.
8. **Copy all details** dabayein. Poora message copy ho jata hai, use WhatsApp ya email me paste karke admin ko bhej dein.
9. Box band ho jaye to page ke upar **Share login details** button se dobara khol sakte hain. Lekin page refresh karne ya kahin aur jaane ke baad password dobara nahi dikhta, kyunki wo kahin save nahi hota.

Admin login ke baad apna password khud badal sakta hai.

Nayi company ko ye sab apne aap mil jata hai, taaki wo turant kaam shuru kar sake:

- Ek default shift: General Shift, 9:00 AM se 6:00 PM
- Sunday weekly off
- Leave types: Casual Leave (12), Sick Leave (12), Paid Leave (15), Unpaid Leave
- Teen roles: Company Admin, HR Manager, Viewer
- Pehla Company Admin, jo aapne form mein bhara

Company ka Admin baad mein ye sab apni zaroorat ke hisaab se badal sakta hai. Dekhein [Company Settings](13-company-settings.md).

### Company ki details badalna

1. **Companies** list mein company ki line par pencil button dabayein, ya company ke page par **Edit** dabayein.
2. Jo badalna hai badlein.
3. **Save company** dabayein. Bina badle wapas jaana ho to **Cancel**.

Yahi details company ka apna Admin bhi apni Settings se badal sakta hai. Edit screen par pehle admin ka naam, email ya password nahi aata, wo sirf company banate waqt bhara jata hai.

### Company ko band karna (deactivate)

1. Company ka page kholein.
2. **Deactivate company** card mein **Deactivate company** button dabayein.
3. Confirmation mein dobara **Deactivate company** dabayein.

Iska asar:

- Us company ka **koi bhi user login nahi kar sakta**. Jo abhi login hain wo agli click par bahar ho jaate hain aur unhe message dikhta hai ki account ya company deactivate ho gayi hai.
- **Kuch bhi delete nahi hota.** Employees, attendance, payroll, sab waisa ka waisa rehta hai.
- Us company par **Sign in as** ke button band ho jaate hain.

### Company ko wapas chalu karna (activate)

1. Company ka page kholein (list mein status **Inactive** chun kar dhoondh sakte hain).
2. **Activate company** button dabayein aur confirm karein.

Users turant dobara login kar sakte hain aur saara data waisa hi milta hai.

### Kisi company ke admin ke roop mein sign in karna

Ye tab kaam aata hai jab company ko setup mein madad chahiye ya koi dikkat samajhni ho.

**Tareeka 1 (list se):** **Companies** list mein company ki line par andar jaate teer wala button dabayein. Aap us company ke Company Admin ke roop mein andar chale jayenge.

**Tareeka 2 (company ke page se):** Company ka page kholein aur upar **Sign in as company admin** dabayein.

Dono tareekon mein aap us company ke sabse purane active Company Admin ke roop mein sign in hote hain aur us company ka Dashboard khulta hai.

### Kisi khaas user ke roop mein sign in karna

1. Company ka page kholein.
2. **Users** table mein us user ki line par **Sign in as {naam}** dabayein.

Ab aapko screen bilkul waisi dikhegi jaisi us user ko dikhti hai, usi ki permissions ke saath. Jaise Viewer ke roop mein jaane par aap sirf dekh payenge, badal nahi payenge. Ye tab kaam ka hai jab koi user kahe "mujhe ye button nahi dikh raha".

### Sign in as ke dauran aur wapas aana

- Screen ke sabse upar peeli patti dikhti rehti hai: "You are signed in as {naam} at {company}. Changes you make are real."
- Is dauran aap jo bhi badlenge wo **asli badlav** hai, us company ke data mein save hoga.
- Kaam khatam hone par patti mein **Return to platform admin** dabayein. Aap wapas apne Super Admin account mein **Companies** screen par aa jayenge.

### Platform Settings badalna

1. Left menu se **Platform Settings** kholein.
2. **Platform name** aur **Support email** bharein.
3. **Defaults for new companies** mein wo **Currency**, **Timezone** aur **Date format** rakhein jo aapki zyadatar companies use karti hain.
4. **Save platform settings** dabayein.

### Apna khud ka password badalna

Upar right corner mein apni photo par click karein → **Security**. Dekhein [Shuruaat aur Login](01-shuruaat-aur-login.md).

## Example

Maan lijiye aap platform owner hain aur aapke paas ek nayi company "Shree Textiles" aayi hai.

1. Aap **Companies** → **Add company** dabate hain.
2. Company name "Shree Textiles", city Surat, currency INR, timezone Asia/Kolkata.
3. **First Company Admin**: Name "Kavita Shah", Email "kavita@shreetextiles.example", aur ek mazboot password.
4. **Create company** dabate hi company ban jati hai, saath mein General Shift, Sunday off, leave types aur roles.
5. Aap Kavita ko email aur password bhej dete hain.

Do din baad Kavita phone karti hain: "HR Meera ko payroll finalize ka button nahi dikh raha."

1. Aap Shree Textiles ka page kholte hain aur **Users** mein **Sign in as Meera Joshi** dabate hain.
2. Upar peeli patti aati hai. Payroll screen dekh kar samajh aa jata hai ki Meera ka role Viewer hai.
3. Aap **Return to platform admin** dabate hain aur Kavita ko batate hain ki Meera ka role HR Manager kar dein.

Teen mahine baad Shree Textiles service band karna chahti hai. Aap company ke page par **Deactivate company** dabate hain. Company ka saara data surakshit rehta hai. Agle saal company wapas aati hai to **Activate company** dabate hi sab kuch pehle jaisa mil jata hai, jaise September ka payroll ₹5,20,000 net payable ke saath.

## Dhyan rakhne wali baatein

- Company **delete nahi hoti**, sirf deactivate hoti hai. Isse purana record kabhi nahi khota.
- Pehle Company Admin ka email poore platform mein alag (unique) hona chahiye. Jo email kisi aur user ka hai wo dobara nahi chalega.
- Password mazboot rakhein: kam se kam 12 akshar, bade aur chhote letter, number aur symbol.
- Company ke baaki users (HR, Viewer) Super Admin nahi banata. Unhe company ka Admin apni Settings ke **Roles & Permissions** se jodta hai.
- **Sign in as** sirf active company ke active user par chalta hai. Company inactive ho to pehle activate karein. Agar company mein koi active Company Admin nahi hai to admin wala button kaam nahi karega, tab kisi doosre active user ke roop mein jayein.
- **Sign in as** ka har use us company ke Audit Logs mein record hota hai ("Super admin signed in as ..."), aur company activate / deactivate karna bhi. Company ka Admin ise dekh sakta hai.
- Sign in as ke dauran kiye gaye badlav us user ke naam se record hote hain jiske roop mein aap andar gaye. Isliye zaroorat se zyada kuch na badlein aur kaam hote hi **Return to platform admin** dabayein.
- Sign in as ke dauran **Log out** karne par aap poori tarah bahar ho jaate hain. Phir apne Super Admin email se dobara login karna hoga.
- Platform Settings ke default sirf **nayi** companies ke form mein pehle se bhare aate hain. Purani companies par inka koi asar nahi hota.
- Har company ki attendance, payroll aur leave ki settings uski apni hoti hain. Platform Settings se wo nahi badalti.
- Phone par companies ki table card ban jati hai aur chaar card do-do ki line mein aate hain. Saare kaam phone se bhi ho sakte hain.

## Aksar pooche jane wale sawal

**Kya main seedha kisi company ke employees ya payroll dekh sakta hoon?**
Companies list aur company ke page par sirf ginti aur total dikhte hain. Andar ka data dekhne ke liye **Sign in as company admin** ka use karein.

**Company Admin apna password bhool gaya, kya karoon?**
Us company mein doosra Company Admin ho to wo **Roles & Permissions** se naya password set kar sakta hai. Nahi to aap **Sign in as company admin** se andar jaakar **Settings** → **Roles & Permissions** mein us user ko edit karke **New password** set kar dein.

**Deactivate karne se company ka data chala jayega?**
Nahi. Sirf login band hota hai. Activate karte hi sab wapas mil jata hai.

**Company ke log bol rahe hain ki login nahi ho raha.**
**Companies** list mein us company ka **Status** dekhein. **Inactive** ho to activate karein. Company active ho to us user ka status company ke page ki **Users** table mein dekhein, wo **Deactivated** ho sakta hai.

**Kya ek company doosri company ka data dekh sakti hai?**
Nahi. Har company ka data poori tarah alag rehta hai.

**Galti se galat company bana di, ab kya?**
Use **Edit** se sahi kar lein, ya **Deactivate company** kar dein. Delete ka option nahi hai.
