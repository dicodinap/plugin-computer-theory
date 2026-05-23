# GraphitoUBB — Accessibility Statement

## Target

WCAG 2.1 Level AA for all student-facing UI surfaces in `mod_graphitoubb` (truth table editor, DFA editor, autosave indicator, conflict resolver).

## Color contrast

- All text on background combinations target a contrast ratio of at least 4.5:1 (WCAG 1.4.3 AA).
- Interactive elements (buttons, radio inputs, focused cells) target 3:1 against adjacent non-text colors (WCAG 1.4.11).
- Feedback overlays (correct/incorrect cell highlights) use color **plus** an icon or text label to avoid reliance on color alone (WCAG 1.4.1).

## Keyboard navigation

### Truth table editor

| Interaction | Key |
|---|---|
| Move between editable cells | `Tab` / `Shift+Tab` |
| Move between cells in grid | Arrow keys |
| Select radio option | Space / Enter |
| Submit form | Enter on submit button |
| Open conflict resolver | Triggered by autosave response; resolved with `Tab` + `Enter` |

All interactive controls receive visible focus indicators (`:focus-visible` styles, not suppressed globally).

### DFA editor (AFD tool)

The Cytoscape.js canvas is a complex widget. Keyboard support is partial in v0.3:

- Toolbar buttons are fully keyboard-operable (`button` elements, `tabindex="0"`).
- Canvas interactions (click to add state, click to connect) require pointer input in the current release — full keyboard canvas support is deferred (tech debt tracked in PARITY.md).
- Screen-reader fallback: ARIA live region announces mode changes (`graphitoubb:modechange` event updates `role="status"` region).

## Screen-reader testing approach

Tested (manually) with:
- **NVDA + Firefox** (Windows) — primary target for Moodle deployments.
- **VoiceOver + Safari** (macOS) — secondary target.

Key landmarks present:
- `<main>` wraps the activity content area.
- Editor toolbar has `aria-label` (string key `toolbar_label`).
- Autosave indicator uses `role="status"` and `aria-live="polite"`.
- Conflict modal uses `role="dialog"` with `aria-labelledby`.

## Automated testing (Behat + axe-core)

axe-core rules are injected in Behat scenarios via the `I check accessibility` step (custom step defined in `mod/graphitoubb/tests/behat/`):

```gherkin
And I check accessibility
```

This step runs `axe.run()` via JavaScript and fails the scenario on any axe violation at level A or AA.

Covered scenarios:
- Student opens truth-table activity (complete mode) → axe clean.
- Teacher views report page → axe clean.
- Conflict resolver dialog opened → axe clean.

axe-core is loaded via CDN in the Behat browser session (not bundled). Requires internet access in CI.

## Known limitations (v0.3)

- DFA canvas is not fully keyboard-accessible. Workaround: use the text-based AFD serializer (not yet exposed in UI) to import automata via JSON.
- axe-core automated tests cover the page shell; complex canvas interactions are out of scope for automated checks.
- High-contrast mode (Windows HCM) not tested; tracked as tech debt.

## Contact

Accessibility issues: file a GitHub issue tagged `a11y`.
