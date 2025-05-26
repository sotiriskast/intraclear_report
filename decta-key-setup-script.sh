#!/bin/bash

# This script sets up the RSA key for Decta SFTP integration

# Create the storage directory for Decta
mkdir -p storage/app/decta

# Check if RSA key file already exists in the target location
if [ -f "storage/app/decta/decta_rsa" ]; then
    echo "RSA key already exists at storage/app/decta/decta_rsa"
else
    # Check if RSA key file exists in current directory
    if [ ! -f "decta_rsa" ]; then
        echo "Error: decta_rsa file not found in current directory."
        echo "Please place the RSA key in the current directory or manually copy it to storage/app/decta/decta_rsa"
        exit 1
    fi

    # Copy the RSA key to the storage location
    cp decta_rsa storage/app/decta/decta_rsa
    echo "RSA key copied to storage/app/decta/decta_rsa"
fi

# Set proper permissions for the key
chmod 600 storage/app/decta/decta_rsa

# Verify the key was properly set up
if [ -f "storage/app/decta/decta_rsa" ]; then
    echo "RSA key successfully set up at storage/app/decta/decta_rsa"
    echo "Key permissions:"
    ls -la storage/app/decta/decta_rsa
else
    echo "Error: Failed to set up RSA key."
    exit 1
fi

# Add key path to .env file if it doesn't exist
if ! grep -q "DECTA_SFTP_PRIVATE_KEY_PATH" .env; then
    echo "Adding SFTP key path to .env file..."
    echo "DECTA_SFTP_PRIVATE_KEY_PATH=storage/app/decta/decta_rsa" >> .env
    echo "Added SFTP key path to .env file."
else
    echo "SFTP key path already exists in .env file."
fi

# Add other required env variables if they don't exist
if ! grep -q "DECTA_SFTP_HOST" .env; then
    echo "Adding SFTP host to .env file..."
    echo "DECTA_SFTP_HOST=files.decta.com" >> .env
    echo "Added SFTP host to .env file."
fi

if ! grep -q "DECTA_SFTP_PORT" .env; then
    echo "Adding SFTP port to .env file..."
    echo "DECTA_SFTP_PORT=822" >> .env
    echo "Added SFTP port to .env file."
fi

if ! grep -q "DECTA_SFTP_USERNAME" .env; then
    echo "Adding SFTP username to .env file..."
    echo "DECTA_SFTP_USERNAME=INTCL" >> .env
    echo "Added SFTP username to .env file."
fi

echo "Decta SFTP integration setup completed."
