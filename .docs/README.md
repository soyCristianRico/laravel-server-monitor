# Laravel Server Monitor Documentation

Comprehensive documentation for the Laravel Server Monitor package.

## 📚 Table of Contents

### Getting Started
- [Installation Guide](installation/README.md) - Step-by-step installation instructions
- [Quick Start](installation/quickstart.md) - Get up and running in 5 minutes

### Configuration
- [Configuration Overview](configuration/README.md) - All configuration options
- [Environment Variables](configuration/environment.md) - .env settings
- [Advanced Configuration](configuration/advanced.md) - Custom setups

### Usage
- [Commands Reference](usage/commands.md) - All available commands
- [Scheduling](usage/scheduling.md) - Automated task scheduling
- [Notifications](usage/notifications.md) - Alert system setup

### Examples
- [Basic Setup](examples/basic-setup.md) - Simple configuration example
- [Advanced Setup](examples/advanced-setup.md) - Production-ready configuration
- [Custom Integrations](examples/integrations.md) - Slack, Teams, etc.

### API Reference
- [Services](api/services.md) - Service classes documentation
- [Commands](api/commands.md) - Command classes documentation
- [Traits](api/traits.md) - Available traits

### Troubleshooting
- [Common Issues](troubleshooting/common-issues.md) - Frequent problems and solutions
- [Performance](troubleshooting/performance.md) - Performance optimization
- [Security](troubleshooting/security.md) - Security considerations

## 🚀 Quick Links

- **[5-Minute Setup](installation/quickstart.md)** - Get started immediately
- **[Commands Overview](usage/commands.md)** - See what the package can do
- **[Configuration](configuration/README.md)** - Customize for your needs
- **[Examples](examples/basic-setup.md)** - Real-world usage examples

## 🎯 Key Features

### Server Monitoring
- **Resource Monitoring**: CPU, memory, disk space
- **Service Monitoring**: MySQL, processes
- **Configurable Thresholds**: Warning and critical levels
- **Real-time Alerts**: Email notifications

### Security Scanning
- **Malware Detection**: PHP pattern scanning
- **Process Monitoring**: Suspicious activity detection
- **File System Monitoring**: System file changes
- **Network Monitoring**: Port scanning
- **Authentication Monitoring**: Failed login tracking

### Alert System
- **Multi-channel Notifications**: Email, Slack (extendable)
- **Role-based Alerts**: Admin user targeting
- **Alert Cooldowns**: Prevent notification spam
- **Detailed Logging**: Comprehensive audit trail

## 🔧 Requirements

- PHP 8.1+
- Laravel 10.x or 11.x
- spatie/laravel-permission
- Linux server environment

## 📝 Quick Example

```bash
# Install
composer require cristian-dev/laravel-server-monitor

# Publish config
php artisan vendor:publish --provider="CristianDev\LaravelServerMonitor\ServerMonitorServiceProvider"

# Test monitoring
php artisan server:monitor

# Test security scan
php artisan security:check
```

## 🆘 Need Help?

- Check [Common Issues](troubleshooting/common-issues.md)
- Review [Examples](examples/basic-setup.md)
- Read [Configuration Guide](configuration/README.md)