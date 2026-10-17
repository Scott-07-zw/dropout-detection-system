# Risk engine (inc/risk.php)
Evaluated per student per module; stored in `risk_state` with the explanation (`trace` JSON). Recalculated after every change to attendance, marks, registration or thresholds.

1. **Financial:** not registered -> RED, final status RED regardless of other factors (other factors are still computed for the report).
2. **Attendance:** trailing run of consecutive missed lectures. Below `att_amber` (5) green; `att_amber`-`att_red-1` amber; `att_red` (10) or more red.
3. **Academic:** coursework = test + assignment 1 + assignment 2, marked on the scale of the module's faculty (copied to its departments; the global `settings` values are only a fallback). All three entered and total below `cw_min` (12) -> red. Otherwise any entered assessment below `amber_pct` (30%) of its maximum -> amber. Otherwise green.
4. **Combine:** any red -> red; two or more ambers -> red (when `escalation` = 1); one amber -> amber; else green.

Interpretation to confirm with your supervisor: the brief's "overall academic mark < 30%" cannot be reached once coursework is 12 or more out of 20, so amber uses the weakest individual assessment instead.

Alerts are raised when a student's status worsens (to amber, red or extreme = two red factors). Lecturers see all; HODs see extreme only.
