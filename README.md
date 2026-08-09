<p align="center">
  <img src="src/assets/logo.png" alt="CentralCorp Panel Installer logo" width="680">
</p>

# CentralCorp Installer

CentralCorp Installer checks a web hosting environment and downloads the latest compatible release of CentralCorp Panel. It is the deployment helper for the self-hosted CentralCorp Minecraft Launcher Panel ecosystem.

[![Latest release](https://img.shields.io/github/v/release/CentralCorp/Installer?label=release)](https://github.com/CentralCorp/Installer/releases/latest)
[![Node.js CI](https://github.com/CentralCorp/Installer/actions/workflows/tests.yml/badge.svg)](https://github.com/CentralCorp/Installer/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-3451b2.svg)](LICENSE)
[![Documentation](https://img.shields.io/badge/docs-installation-0ea5e9.svg)](https://centralcorp.github.io/en/install/prerequis)

**[Installation guide](https://centralcorp.github.io/en/install/prerequis)** · **[Panel](https://github.com/CentralCorp/centralpanel-v2)** · **[Launcher](https://github.com/CentralCorp/CentralCorp-Launcher)** · **[Latest release](https://github.com/CentralCorp/Installer/releases/latest)**

![CentralCorp Installer requirements and panel deployment screen](https://centralcorp.github.io/img/installer.png)

## What does it do?

The installer provides a Vue interface backed by a PHP entry point. It:

- checks PHP 8.2 or newer;
- verifies required PHP extensions and functions;
- checks write permissions and URL rewriting support;
- retrieves the latest `panel-*.zip` asset from CentralCorp Panel releases;
- downloads, extracts and prepares the panel files;
- redirects the operator to the panel setup process.

It installs the web panel, not the Electron Minecraft launcher itself. Launcher configuration and compilation remain separate documented steps.

## Requirements

- a web server executing PHP 8.2 or newer;
- the PHP extensions reported by the requirements screen;
- writable hosting storage;
- outbound HTTPS access to retrieve the panel release;
- a database supported by CentralCorp Panel for the subsequent setup.

See the [CentralCorp prerequisites](https://centralcorp.github.io/en/install/prerequis) before deployment.

## Installation

Download `installer.zip` from the [latest Installer release](https://github.com/CentralCorp/Installer/releases/latest), upload it to the target web root and extract it. Open the installation URL in a browser, resolve any failed requirement, then continue to the panel download.

The complete hosting workflow is documented in [Install CentralCorp web panel](https://centralcorp.github.io/en/install/step3).

## Development

```bash
npm install
npm run dev
```

Available verification and production commands include:

```bash
npm run type-check
npm run build
npm run lint
npm run package
```

`npm run package` builds the Vue frontend, copies hashed assets into the PHP backend and creates `installer.zip`. The packaging command rewrites generated asset references and should be reviewed in a clean worktree after use.

## CentralCorp ecosystem

- [Website and documentation](https://github.com/CentralCorp/centralcorp.github.io) — product pages and installation guides
- [CentralCorp Launcher](https://github.com/CentralCorp/CentralCorp-Launcher) — customizable player application
- [CentralCorp Panel](https://github.com/CentralCorp/centralpanel-v2) — self-hosted Minecraft Launcher Panel
- [CentralCorp Installer](https://github.com/CentralCorp/Installer) — this hosting and deployment helper

## License

CentralCorp Installer is distributed under the [MIT License](LICENSE). The repository license permits use, modification and distribution, including commercial use, provided the copyright and permission notice are retained.
