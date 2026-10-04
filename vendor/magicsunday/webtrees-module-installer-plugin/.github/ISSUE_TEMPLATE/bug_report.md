---
name: Bug Report
about: Report an issue with the Webtrees Module Installer Plugin
title: '[BUG] '
labels: 'bug'
assignees: ''

---

## Bug Description
<!-- A clear and concise description of what the bug is -->

## Environment Information
- **PHP Version**: <!-- e.g., 8.2.0 -->
- **Composer Version**: <!-- e.g., 2.6.5 -->
- **Operating System**: <!-- e.g., Windows 11, Ubuntu 22.04, macOS 13 -->
- **Webtrees Version** (if applicable): <!-- e.g., 2.1.18 -->

## Module Information
<!-- If the issue is related to a specific module installation -->
- **Module Name**: <!-- e.g., fancy-treeview -->
- **Module Version**: <!-- e.g., 2.0.1 -->
- **Module Repository**: <!-- e.g., https://github.com/vendor/module-name -->

## Steps to Reproduce
1. <!-- First step -->
2. <!-- Second step -->
3. <!-- And so on... -->

## Expected Behavior
<!-- A clear and concise description of what you expected to happen -->

## Actual Behavior
<!-- What actually happened, including any error messages, logs, or screenshots -->

## Composer Configuration
<!-- Please include relevant parts of your composer.json file -->
```json
{
  "require": {
    "magicsunday/webtrees-module-installer-plugin": "^1.6"
  },
  "config": {
    "allow-plugins": {
      "magicsunday/webtrees-module-installer-plugin": true
    }
  }
}
```

<!-- You may have other dependencies in your actual composer.json -->

## Console Output
<!-- If applicable, add console output when running composer commands -->
```
$ composer require vendor/module-name
// output...
```

## Additional Context
<!-- Add any other context about the problem here -->

## Possible Solution
<!-- If you have suggestions on how to fix the issue -->