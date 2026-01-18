# Mayo Events Manager

[![codecov](https://codecov.io/gh/bmlt-enabled/mayo/branch/main/graph/badge.svg)](https://codecov.io/gh/bmlt-enabled/mayo)

A WordPress plugin for managing community events with support for recurring schedules, service body integration, and email notifications.

See [readme.txt](readme.txt) for full plugin documentation.

## Development

### Requirements
- PHP 8.2+
- Node.js
- Composer

### Setup
```bash
composer install
npm install
npm run build
```

### Commands
| Command | Description |
|---------|-------------|
| `make lint` | Run PHP CodeSniffer |
| `make fmt` | Auto-fix PHP linting issues |
| `make test` | Run PHPUnit tests |
| `make coverage` | Run tests with code coverage |
| `npm run build` | Build production JS bundles |
| `npm run dev` | Build with watch mode |

### Testing
Tests use [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) to mock WordPress functions, allowing fast unit tests without a WordPress installation.

```bash
make test
```

## License

GPLv2 or later
