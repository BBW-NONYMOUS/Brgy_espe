# BHW User Manual — Outline

Barangay Health Resident Profiling System — Barangay Health Center, Kalawag II, Isulan, Sultan Kudarat.

This is the **outline** of the manual for Barangay Health Workers. Each section lists what the finished page will
teach and which screenshot it uses (`docs/screenshots/…`).

**How the manual should be written:**

- simple English with Filipino terms where staff use them (_purok_, _buntis_, _bakuna_);
- one task per page, numbered steps, one screenshot per step group;
- print it A5, spiral-bound, for the health center;
- keep a **one-page quick card** (§17) at each desk and in each tablet case.

What a BHW can do in the system (role defaults):

| Can                                                                  | Cannot (Midwife or Admin does it)                                   |
| -------------------------------------------------------------------- | ------------------------------------------------------------------- |
| Register and update residents, record consent, archive (with reason) | Permanently delete records, manage accounts, change settings        |
| Manage households, geo-tag homes (with consent)                      | Register pregnancies, record deliveries                             |
| Record visits, vital signs, vaccines, weights/heights, vitamin A     | Classify disease cases (confirm / rule out); manage outbreak alerts |
| Report suspected disease cases                                       | Create or update referrals (BHWs can view them and print the slip)  |
| Dispense medicines (FEFO is automatic)                               | Receive, adjust or write off stock                                  |
| Book and update appointments; plan programs and take attendance      | Edit prenatal / immunization schedules                              |
| View reports and export them                                         |                                                                     |

---

## 1. Getting started

- What the system is for; who uses it (BHW, Midwife, Admin, Barangay Officials).
- Opening it: the address `https://…` on the desk PC or tablet; bookmark / home-screen icon.
- **First login:** username from the Admin, temporary password, then setting your own password (rules: 10+ characters,
  upper- and lower-case letters, numbers).
- Locked out after 5 wrong tries: wait 15 minutes or ask the Admin.
- Logging out; the system logs you out after 30 minutes of no use.
- Screens: sidebar groups (Overview, Records, Health, Services, Reports), the bell, your name menu.
- _Screenshots:_ login, change password, dashboard.

## 2. Privacy rules for BHWs (RA 10173) — read first

- Health information is confidential: ask only what the form needs, and only for the health center's work.
- **Consent first:** explain, get the signature or thumbmark, record it. Separate consents: registration, geo-tagging,
  family planning.
- Never share screenshots or photos of records, or exports, in group chats or on Facebook.
- Lock or log out when you leave the tablet; keep it in its case; report a lost tablet to the Admin **the same day**.
- Everything you open, print or export is recorded in the audit log.
- Printed slips and summaries: give them to the person, never leave them on the desk.

## 3. Your day at a glance

- Dashboard: today's appointments, missed ones, outbreak banner, medicines expiring, upcoming programs.
- The bell: what each notification means and what to do (outbreak, overdue care, missed appointments, program reminder).
- Calendar: day / week / month; colours by type.

## 4. Residents

- Searching: by name (first letters), resident ID, household number, birthdate; filters (purok, sex, age group, flags).
- **Registering a resident:** the steps of the form, required fields, consent; the **duplicate warning** (same name
  and birthdate) and what to do (open the existing record instead).
- Updating details; what the badges mean (Senior, PWD, 4Ps, Pregnant, High-risk).
- Archiving (moved away, died) with a reason; finding archived residents with the archive filter and restoring them.
- _Screenshots:_ residents list, registration steps, duplicate dialog, profile header.

## 5. Households and the map

- Creating a household; adding members; head of household; water source and toilet type; 4Ps.
- **Geo-tagging in the field** (tablet):
    1. geo-tagging consent recorded;
    2. stand at the house;
    3. **Use my location** and wait for good accuracy;
    4. or tap the map;
    5. save.
       Removing a location when consent is withdrawn.
- Reading the map: marker colours (pregnant, under 5, senior, active case, critical alert), filters, legend.
- Without internet the map is grey, but the lists still work.
- _Screenshots:_ household page, geo-tag dialog, map with legend.

## 6. Emergency contacts and medical alerts

- Adding emergency contacts; one primary contact.
- Medical alerts (allergy, condition, other); **critical** alerts show as a red banner everywhere: check it before
  giving any medicine.

## 7. Health visits

- Recording a visit: type, complaint, vital signs (with the allowed ranges), notes.
- The visits list: finding visits by date, purok, type, "Attended by me".

## 8. Mothers (maternal tracker)

- Reading the tracker: AOG, EDD, risk level, next contact, **overdue** badge.
- The schedule of 8 prenatal contacts; recording a check-up on the scheduled contact.
- High-risk mothers: what to watch for and when to call the Midwife.
- Postnatal visits after delivery (the Midwife records the delivery).

## 9. Children under five

- Immunization checklist: due, overdue, given; tap a due dose to record it; why a dose may move later (minimum
  interval).
- Weighing (OPT Plus): weight, height, MUAC; choosing the nutritional status; the growth chart.
- Vitamin A (every 6 months from 6 months of age) and deworming.
- The child-health list chips: Overdue vaccines, Due now, Not normal nutrition, Vitamin A due.

## 10. Reporting a disease case

- When to report (notifiable diseases list), what to fill in (onset date, purok).
- Your report is saved as **Suspected**; the Midwife classifies it.
- Outbreak banner: what it means for the purok; Officials see counts only.

## 11. Referrals

- Viewing a referral and its status steps (Pending → Sent → Received → Completed).
- Printing the **referral slip** for the patient; the return slip the facility sends back.

## 12. Medicines

- Finding a medicine; the expiry colours (red expired, orange ≤ 30 days, yellow ≤ 90 days).
- **Dispensing:** choose the resident first (their critical alerts appear), then the quantity; the system picks the
  batch that expires first; you cannot give more than is in stock.
- Low stock: tell the Midwife.

## 13. Appointments

- Appointments made automatically (prenatal, immunization, postnatal) and booking one by hand.
- Marking completed / missed / cancelled; missed appointments are re-booked.

## 14. Community health programs

- Planning a program (date, venue, target group, puroks); the program calendar.
- Taking attendance on the day (eligible residents list).

## 15. Reports and printing

- Reports page: choosing the report, date range and purok; PDF / Excel / CSV.
- Name lists in reports are confidential (lock badge); share reports only with the Midwife or Admin.
- Printing the **family health summary** for a household.

## 16. Problems and who to call

- Common problems in plain words: forgot password, locked account, GPS not working, page says "expired", no
  internet (map only), printer.
- Who to call: Admin, then barangay IT support; their numbers.

## 17. Appendices

- **Quick card** (one page): log in → find resident → record visit / vaccine / weight → dispense → log out.
- Glossary: AOG, EDD, LMP, ANC, EPI, OPT Plus, MUAC, FEFO, PIDSR, 4Ps, RHU.
- EPI schedule table (BCG, HepB at birth; Penta/OPV/PCV at 6-10-14 weeks; IPV at 14 weeks and 9 months; MMR at 9 and
  12 months).
- The 8 prenatal contacts by week.
- Consent form wording (registration, geo-tagging, family planning).
