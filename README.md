![logo](/assets/images/ophellia.png)
![screenshot](/assets/images/2.png)

<p align="center">
A cutting-edge PHP 7.4+ webshell designed for advanced penetration testing and educational exploration. Harness its capabilities responsibly – illegal use is strictly prohibited. Elevate your cybersecurity skills with this powerful tool, crafted for those who seek knowledge and push the boundaries of web security.<br/><br/>
<img src="https://img.shields.io/badge/PHP-7.4.X-bf616a?style=flat-square" alt="PHP"/>
<img src="https://img.shields.io/badge/PHP-8.X.X-88c0d0?style=flat-square" alt="PHP"/>
<img src="https://img.shields.io/badge/LICENSE-GPL3.0-ebcb8b?style=flat-square" alt="License"/>
<img src="https://img.shields.io/badge/VERSION-2.0.0-a3be8c?style=flat-square" alt="Version"/>
</p>

## _V2.0.0 - 'HoneyComeBear'_

- **What's new?**
  - [x] Restructured code into object-oriented design with `Elliottophellia` class
  - [x] Enhanced security with improved password handling and secure file operations
  - [x] Implemented comprehensive error handling with try-catch blocks
  - [x] Upgraded UI with more clean and modern styles and modal dialogs for success and error messages
  - [x] Added new advanced command execution function
  - [x] Improved system information display
  - [x] Enhanced file manager with more detailed file information
  - [x] Updated network tools implementation
  - [x] Improved file editing and creation forms
  - [x] Introduced version control with `VERSION` constant
  - [x] Improved responsive design for better mobile compatibility
  - [x] Integrated Font Awesome icons and Google Fonts for enhanced visuals
  - [x] Updated footer with detailed copyright information and relevant links
  - [x] Removed code obfuscation for improved readability and maintainability
  - [x] Added download functionality to file manager
  - [x] Upgraded password hashing mechanism from MD5 to bcrypt using password_hash() and password_verify()


## _scans_

| VENDOR | RESULT | DESCRIPTION | DATE |
|--------|--------|-------------| -----|
| VirusTotal | 0/65 | No security vendors flagged this file as malicious | 2024-08-25 11:10:18 UTC |
| Hybrid Analysis | 0/24 | AV Detection: Marked as clean | 08/25/2024 11:15:44 (UTC) |
| ClamAV | 0/8697733 | Scanned files: 1 Infected files: 0 | 2024:08:25 18:42:30 |

## _Features_

### _File Management_
- Comprehensive file operations:
  - Rename files and directories
  - Delete files and directories
  - Edit file contents
  - Download files
- Detailed file information:
  - Size
  - Permissions
  - Owner/Group
  - Last modified timestamp

### _File Upload_
- Flexible upload options:
  - Upload to current path
  - Upload to server root directory

### _Networking_
- Advanced shell capabilities:
  - Bind Shell (C, Perl, Ruby, Python)
  - Reverse Shell (C, Perl, Ruby, Python)

### _Communication_
- Integrated mailing functionality:
  - Send emails directly from the interface

### _System Information_
- Comprehensive system details:
  - User information
  - System specifications
  - Server IP address
  - Client IP address
  - PHP Safe Mode status
  - Available disk space
  - Disabled PHP functions

### _File and Directory Creation_
- Create new files with custom content
- Create new directories

### _Command Execution_
- Execute system commands directly from the interface

### _Security_
- Login system:
  - Default password: honeyconmebear

## _screenshot_

|   |   |
|------------|------------|
| ![screenshot 1](/assets/images/1.png) | ![screenshot 2](/assets/images/2.png) |
| ![screenshot 3](/assets/images/3.png) | ![screenshot 4](/assets/images/4.png) |
| ![screenshot 5](/assets/images/5.png) | ![screenshot 6](/assets/images/6.png) |

## _contribute_

We welcome contributions to this project. If you'd like to contribute, please follow these steps:

1. Fork the repository
2. Create a new branch for your feature or bug fix
3. Make your changes and commit them with clear, descriptive messages
4. Push your changes to your fork
5. Submit a pull request to the main repository

For major changes, please open an issue first to discuss what you would like to change.

If you have any questions or need further assistance, you can reach out to the project maintainer:

- Email: [me@rei.my.id](mailto:me@rei.my.id)
- Twitter: [@elliottophellia](https://twitter.com/elliottophellia)

We appreciate your interest in improving this project!

## _reference_

- [WSO](https://github.com/mIcHyAmRaNe/wso-webshell)
- [MARIJUANA](https://github.com/0x5a455553/MARIJUANA)
- [INDOXPLOIT](https://github.com/linuxsec/indoxploit-shell)

## _license_

```
Ophellia
Copyright (C) 2024  Reidho Satria

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
```

## _disclaimer_

The user of this web shell bears full responsibility for any actions or activities related to the materials contained herein. Misuse of the information provided in this web shell may result in criminal charges against the individuals involved. The author explicitly disclaims any liability for criminal charges or legal consequences arising from the misuse of this web shell's information for unlawful purposes.