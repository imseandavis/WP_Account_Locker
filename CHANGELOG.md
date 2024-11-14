# Change Log
All notable changes to this project will be documented in this file.

## [1.0.0] - 2024-02-06
Initial release

### Added
- Basic account locking functionality
- User profile lock/unlock toggle
- Bulk actions for locking/unlocking multiple accounts
- Status column in users list
- Custom lock message setting in General Settings
- Prevention of self-account locking
- Warning messages for attempted self-account locking
- Bulk action status messages
- Admin UI improvements
- Security checks and validations

### Features
- Lock/unlock individual user accounts

## [1.0.1] - 2024-02-08

### Added
- Account status filters in users list
- Active accounts count display
- Locked accounts count display
- Ability to filter users by account status
- Visual indicators for status filters

### Enhanced
- User interface improvements for status filtering
- Quick access to locked/active user lists
- Status count visibility

## [1.0.2] - 2024-02-09

### Added
- Activity log for account lock/unlock actions
- Admin-only activity log page
- Tracking of who performed each action
- Timestamp logging for all actions
- Activity log limited to last 50 entries per user

### Enhanced
- Admin interface for viewing activity log
- Security checks for activity log access

## [1.0.3] - 2024-02-10

### Added
- Deactivation cleanup prompt
- Option to remove all plugin data on deactivation
- Modal interface for deactivation choices
- Database cleanup functionality

### Enhanced
- Plugin cleanup process
- User experience during deactivation

## [1.0.4] - 2024-02-11

### Fixed
- Activity logging for lock/unlock actions
- Activity logging for bulk actions
- Connection between activity log and action handlers

### Enhanced
- Logging system implementation
- Activity tracking reliability

## [1.0.5] - 2024-02-12

### Fixed
- Critical error in bulk actions
- Activity log initialization timing
- Plugin dependency handling

### Enhanced
- Error handling in bulk operations
- Plugin initialization process

## [1.0.6] - 2024-02-13

### Added
- Immediate logout on account lock
- Session termination for locked accounts
- Automatic session cleanup

### Enhanced
- Security for locked accounts
- User session management
- Lock action effectiveness

## [1.0.7] - 2024-02-14

### Fixed
- Syntax error in bulk actions handler
- Missing brace in force logout code
- Critical error during bulk operations

## [1.0.8] - 2024-02-14

### Fixed
- Bulk action handling for lock/unlock operations
- WordPress bulk action parameter processing
- Critical error in bulk operations
- User ID validation in bulk actions

### Enhanced
- Bulk action security
- Input sanitization
- Error handling

## [1.0.9] - 2024-02-14

### Fixed
- Bulk lock operation error
- Session handling in bulk actions
- Force logout implementation

### Enhanced
- Bulk action reliability
- Session management

## [1.1.1] - 2024-02-14

### Added
- Enhanced activity log interface
- Performed By filter in activity log
- Sort indicators for all columns
- Responsive design improvements
- Mobile-friendly adjustments

### Changed
- Reduced pagination to 15 items per page
- Improved filter layout and usability
- Enhanced table styling
- Better sort indicator visibility

### Fixed
- Activity log critical error
- Filter handling in activity log
- Sort functionality issues