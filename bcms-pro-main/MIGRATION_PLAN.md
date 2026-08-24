# BCMS Migration Plan: Yii to Laravel

## Overview
This document outlines the migration plan from the BCMS-OLD Yii application to the bcms-pro Laravel application.

## Migration Status

### ✅ Completed
- AccountController (partially migrated)
- Basic Laravel structure and authentication

### ✅ Completed
- AccountController (partially migrated)
- Basic Laravel structure and authentication
- VehicleController (fully migrated with comprehensive functionality)

### 📋 Pending Migration

#### High Priority Controllers
1. **VehicleController** - Core vehicle management
   - Status: ✅ Fully migrated with comprehensive functionality
   - Migrated methods: create, query, update, activate/deactivate, exempt/remove-exempt, bulk operations
   - Dependencies: AccountVehicle, BodyType, RfidTag models

2. **TransactionController** - Core transaction processing
   - Status: Not migrated
   - Key methods: create, query, reconciliation, reporting
   - Dependencies: Vehicle, Account, Receipt models

3. **ReceiptController** - Receipt generation and management
   - Status: Basic structure exists
   - Key methods: generate, print, reprint, verification
   - Dependencies: Transaction, Vehicle models

4. **ReportController** - Reporting and analytics
   - Status: Basic structure exists
   - Key methods: daily reports, reconciliation reports, custom reports
   - Dependencies: Transaction, Vehicle, Account models

#### Medium Priority Controllers
5. **BridgeSubscriptionsController** - Subscription management
   - Status: Not migrated
   - Key methods: create subscription, manage bundles, billing
   - Dependencies: Account, Vehicle, Bundle models

6. **ReconciliationController** - Financial reconciliation
   - Status: Not migrated
   - Key methods: daily reconciliation, variance reports
   - Dependencies: Transaction, Receipt models

7. **StatusCheckController** - System status and health checks
   - Status: Not migrated
   - Key methods: system status, health checks, monitoring
   - Dependencies: Various system models

#### Low Priority Controllers
8. **Auth Controllers** (AuthUserController, AuthRoleController, etc.)
   - Status: Basic Laravel auth exists
   - Key methods: user management, role management, permissions
   - Dependencies: User, Role, Permission models

9. **Supporting Controllers**
   - LaneController - Lane management
   - BodyTypeController - Vehicle body types
   - PriceListController - Pricing management
   - TopUpController - Account top-ups

## Migration Strategy

### Phase 1: Core Business Logic (Week 1-2)
1. Complete VehicleController migration
2. Migrate TransactionController
3. Migrate ReceiptController
4. Set up proper API routes

### Phase 2: Reporting & Analytics (Week 3)
1. Migrate ReportController
2. Migrate ReconciliationController
3. Set up reporting infrastructure

### Phase 3: Advanced Features (Week 4)
1. Migrate BridgeSubscriptionsController
2. Migrate StatusCheckController
3. Enhance authentication and authorization

### Phase 4: Supporting Features (Week 5)
1. Migrate remaining controllers
2. Optimize performance
3. Testing and bug fixes

## Key Migration Patterns

### 1. Controller Structure
```php
// Yii Pattern
class VehicleController extends ActiveController {
    public function actionCreate() {
        // Yii logic
    }
}

// Laravel Pattern
class VehicleController extends Controller {
    public function create(Request $request) {
        // Laravel logic
    }
}
```

### 2. Model Relationships
```php
// Yii Pattern
$vehicle = Vehicle::findOne(['plate_no' => $plate_no]);

// Laravel Pattern
$vehicle = Vehicle::where('plate_no', $plate_no)->first();
```

### 3. Response Format
```php
// Yii Pattern
return array('status' => 1, 'data' => $model);

// Laravel Pattern
return $this->sendResponse($model, 'Success message');
```

## Database Considerations
- Ensure all necessary migrations exist
- Update model relationships
- Maintain data integrity during migration
- Consider data migration scripts if needed

## Testing Strategy
1. Unit tests for each migrated controller
2. Integration tests for API endpoints
3. End-to-end testing for critical workflows
4. Performance testing for high-traffic endpoints

## Notes
- Maintain backward compatibility where possible
- Document all API changes
- Consider gradual rollout strategy
- Monitor performance during migration
