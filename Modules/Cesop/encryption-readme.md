# CESOP Reporting - Encryption Requirements

## Overview

This document clarifies the encryption and security requirements for submitting CESOP (Central Electronic System of Payment Information) XML reports by Payment Service Providers (PSPs).

## Encryption Requirements

The CESOP payment data XML files **do not need to be manually encrypted** before submission. The security and encryption aspects are generally handled by the transmission systems themselves rather than requiring PSPs to handle encryption directly.

### Submission Process Security

1. **PSP to National Tax Administration**:
   - Your National Tax Administration defines their own secure submission method
   - This typically involves a secure portal, SFTP, or another secure file transfer mechanism
   - Standard TLS encryption is usually applied at the transport level
   - The XML file itself is typically submitted unencrypted

2. **National Tax Administration to CESOP**:
   - This transmission uses the AS4/TAPAS platform which handles encryption automatically
   - The encryption/decryption process is managed at the system level
   - Error code "50020: Failed Decryption" in the schema indicates this system-level handling

### Additional Security Measures

Depending on your National Tax Administration, additional security measures may include:

- **Digital Signatures**: 
  - Some Member States may require digital signatures on the submitted files
  - Error code "50040: Failed Signature Check" is related to this validation
  - If required, your National Tax Administration will provide specific instructions

- **Secure Transmission Channels**:
  - Most administrations provide secure portals or APIs for submission
  - These channels typically handle the security requirements automatically

- **Threat and Virus Scanning**:
  - Submitted files are scanned before processing (error codes 50050/50060)
  - Ensure your files are free from malicious content

## What You Need to Do

1. Generate your XML file according to the CESOP schema
2. Validate the file against the XSD and business rules
3. Submit the raw XML file through your National Tax Administration's designated submission channel
4. Follow any country-specific security requirements they may provide

## Country-Specific Requirements

Security and submission requirements can vary by country. Contact your National Tax Administration for specific guidance on:

- Preferred submission channels
- Any additional security requirements
- Digital signature specifications (if required)
- Authentication procedures for their submission system

## Technical Support

For technical issues related to encryption or secure transmission:

1. First contact your National Tax Administration's technical support
2. Reference the error codes in the CESOP schema if encountering security-related rejections

## Important Note

While the XML file itself typically doesn't require manual encryption, always ensure you're using secure channels for transmission and following your country's specific security protocols.
