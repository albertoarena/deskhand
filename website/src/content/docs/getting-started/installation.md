---
title: Installation
description: Install deskhand as a global Composer tool or a standalone PHAR.
---

deskhand operates **on** Laravel projects from the outside — it is not a project
dependency. It carries its own dependencies, so it never relies on the target
project's autoloader.

## Requirements

- PHP **8.3+** — this is the PHP on *your* machine (deskhand is a global CLI),
  not the version your project pins.
- **Git** and **Composer** on your `PATH`.
- **macOS or Linux.** Windows is not supported.
- A **MySQL client** — only if you use `--db=mysql`.

deskhand checks the running PHP version at startup and exits with a clear message
(never a stack trace) if it is below 8.3.

## Global Composer tool

```bash
composer global require albertoarena/deskhand
```

Make sure Composer's global `bin` directory is on your `PATH` (commonly
`~/.composer/vendor/bin` or `~/.config/composer/vendor/bin`), then:

```bash
deskhand --version
```

## Standalone PHAR

Download the `deskhand.phar` attached to the latest
[GitHub Release](https://github.com/albertoarena/deskhand/releases), then make it
executable:

```bash
chmod +x deskhand.phar
./deskhand.phar --version

# optional: install it onto your PATH
mv deskhand.phar /usr/local/bin/deskhand
```

## Next steps

- [Quickstart](/deskhand/getting-started/quickstart/) — provision your first
  isolated worktree.
- [Configuration](/deskhand/getting-started/configuration/) — the `deskhand.yaml`
  reference (zero-config works too).
