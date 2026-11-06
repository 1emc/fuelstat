# Accessibility at Fuelstat

Fuelstat aims to be accessible and usable by as many people as possible, regardless of their abilities, devices, or preferred way of interacting with the application.

Accessibility is an ongoing process. We want to identify and remove barriers as the project evolves.

This document describes our accessibility goals, development guidelines, and how to report accessibility issues.

## Our Commitment

We aim to make Fuelstat usable for people with different needs, including people who:

- Use screen readers or other assistive technologies.
- Navigate applications primarily using a keyboard.
- Have visual impairments, including color blindness or low vision.
- Have motor or mobility impairments.
- Experience cognitive or learning-related disabilities.
- Use mobile devices, alternative input methods, or browser accessibility features.

We consider accessibility an important part of usability, not an optional addition.

## Accessibility Goals

Fuelstat aims to follow the Web Content Accessibility Guidelines (WCAG) 2.2, Level AA, where reasonably applicable.

Our development priorities include:

- Clear and consistent navigation.
- Semantic HTML and meaningful document structure.
- Keyboard accessibility for interactive elements.
- Visible and understandable focus indicators.
- Sufficient color contrast.
- Text alternatives for meaningful images and icons.
- Accessible forms with properly associated labels.
- Clear error messages and helpful instructions.
- Responsive layouts that support zooming and different screen sizes.
- Compatibility with common assistive technologies.
- Accessible interactions in both browser and installed PWA environments.

These are development goals and do not constitute a claim of full WCAG 2.2 AA compliance.

## Development Guidelines

Contributors are encouraged to consider the following principles when implementing or modifying features.

### Semantic HTML

- Use appropriate HTML elements for their intended purpose.
- Use headings in a logical hierarchy.
- Prefer native buttons, links, inputs, and form controls over custom interactive elements.
- Ensure interactive elements have meaningful accessible names.
- Avoid using visual styling as a substitute for semantic structure.

### Keyboard Navigation

- All interactive functionality should be accessible using a keyboard.
- Provide visible focus indicators.
- Maintain a logical tab order.
- Do not create keyboard traps.
- Ensure dialogs, menus, tabs, and other interactive components can be operated without a mouse.

### Color and Contrast

- Do not use color as the only means of communicating information.
- Ensure text and interactive elements have sufficient contrast.
- Consider both light and dark themes.
- Make sure charts and statistics remain understandable without relying exclusively on color.

### Forms and Validation

- Associate form labels with their respective controls.
- Clearly identify required fields.
- Provide understandable validation and error messages.
- Do not rely exclusively on color to indicate errors.
- Preserve user input when validation fails whenever possible.

### Images, Icons, and Charts

- Provide meaningful alternative text for informative images.
- Use empty alternative text for purely decorative images.
- Ensure icon-only buttons have accessible names.
- Provide text-based alternatives or summaries for important chart information.

### Mobile and PWA

- Ensure touch targets are sufficiently large and appropriately spaced.
- Support device orientation and different viewport sizes.
- Avoid interactions that depend exclusively on gestures.
- Provide an alternative to swipe-based navigation.
- Ensure accessibility features continue to work when Fuelstat is installed as a PWA.

## Testing Accessibility

When contributing new functionality or changing existing interfaces, consider testing:

- Keyboard-only navigation.
- Visible focus indicators.
- Screen reader compatibility.
- Text scaling and browser zoom.
- Color contrast in light and dark themes.
- Mobile usability and touch interactions.
- Form labels, validation messages, and error handling.

Automated accessibility tools can help identify common issues, but manual testing is also important.

## Reporting Accessibility Issues

If you encounter an accessibility barrier in Fuelstat, we appreciate your feedback.

Please report accessibility issues through the [GitHub issue tracker](https://github.com/FuelStat/fuelstat/issues).

When reporting an issue, please include:

- A description of the accessibility barrier.
- The affected page, feature, or interaction.
- The device, operating system, and browser used.
- The assistive technology involved, if applicable.
- Steps to reproduce the issue.
- Screenshots or recordings, if helpful and safe to share.

Please do not include personal or sensitive information in public reports.

For security-related issues, use the private reporting process described in [SECURITY.md](SECURITY.md).

## Contributing to Accessibility

Accessibility improvements are welcome!

You can help by:

- Reporting accessibility barriers.
- Suggesting improvements to existing interfaces.
- Testing features with assistive technologies.
- Improving labels, contrast, focus handling, and semantic markup.
- Contributing code that makes Fuelstat more accessible.

Please refer to [CONTRIBUTING.md](CONTRIBUTING.md) for general contribution guidelines.

## Further Resources

- [Web Content Accessibility Guidelines (WCAG) 2.2](https://www.w3.org/TR/WCAG22/)
- [WAI-ARIA Authoring Practices Guide](https://www.w3.org/WAI/ARIA/apg/)
- [Web Accessibility Initiative (WAI)](https://www.w3.org/WAI/)
- [MDN: Accessibility](https://developer.mozilla.org/en-US/docs/Web/Accessibility)

---

Accessibility is an ongoing commitment. Every improvement helps make Fuelstat more inclusive and easier to use.

**Fuelstat**  
[GitHub Repository](https://github.com/FuelStat/fuelstat)
