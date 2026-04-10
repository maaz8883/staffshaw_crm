# System flow — short overview

## Admin side

1. **Login** — User admin panel mein login karta hai.
2. **Naya payment link** — Create par naam / notes likh kar **QR wali image upload** hoti hai (jo bank / Raast standee par hai). System image se QR **read** karke andar **save** kar leta hai.
3. **List** — Saare links ek jagah dikhte hain; search / sort / pages hain.
4. **Detail (Show)** — Har link par **pay URL** milta hai — yahi link **customer ko bhejni** hoti hai; yahan se **copy** bhi kar sakte ho. **QR preview** bhi yahi dikhta hai.
5. **Edit** — Naam, notes, on/off; agar QR badalna ho to **naya image** upload.
6. **Off karna** — Link **inactive** kar do to purana URL **open nahi** hoga (customer ko error / 404).
7. **Profile** — Apna naam, email, password change.

---

## Customer side (jin ko link bhejni hai)

1. Unhe **wahi pay URL** do jo admin **Show** page se copy hoti hai (`.../pay?data=...`).
2. Wo link browser mein kholenge.
3. Screen par **sirf ek QR** dikhega — yeh system usi payment data se **banata** hai jo admin ne save ki thi.
4. Customer apni **banking app** se us QR ko **scan** karke pay karega.

---

## QR code generate kaise hota hai (overview)

Do hisse hain — pehla **read**, doosra **generate**:

### 1) Admin jab image upload karta hai (read / decode)

- Jo **photo** tum bank ke standee / slip ki upload karte ho, us mein jo **QR box** hota hai, software us **image** ko padhta hai.
- Us pattern se andar ka **text** nikalta hai — yeh wahi long string hoti hai jo bank ne QR ke andar chipkayi hoti hai (payment / EMV style data).
- Yeh text **save** ho jati hai; isi se baad mein QR dubara banta hai.

**Yahan “generate” nahi hota** — yahan sirf **purane QR se data read** ho raha hai.

### 2) Customer jab pay link kholta hai (generate)

- Link ke andar woh payment data **encrypted** form mein hoti hai; server use **decrypt** karke wahi **text** pa leta hai jo upar save hui thi.
- Phir software us text se **naya QR pattern** banata hai — black–white modules wali **image** (PNG) jo screen par dikhti hai.
- Yeh har baar **software se draw** hota hai: print ki hui poster ki **photo copy** customer ko nahi dikhayi jati, sirf **usi data** ka **naya QR** banta hai taake scan sahi kaam kare.

**Short:** pehle image se **data nikalna**, phir customer page par us data se **QR picture banana** — dono steps milkar poora flow complete karte hain.

---

## Seedhi baat

- Admin **QR image** deta hai → system andar **payment text** save karta hai → **encrypted link** banati hai.  
- Customer **link** kholta hai → **QR** dikhta hai → **scan** → payment.

---

## Dhyan dena

- Link **sirf tab kaam** karegi jab link **active** ho aur andar **sahi QR data** save ho. Galat / khali data par page nahi khulega.
- Purani links agar **band** kar diye gaye hon to dobara **Show** se naya URL copy karna padega.
