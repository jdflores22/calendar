# Continue: Cluster-Office-Division Implementation

## Context Summary

We are implementing a 3-level organizational hierarchy for the COROPOTI calendar system:

**Level 1: Cluster** (e.g., ODDG-PP, ODDG-AI, ODDG-SC)
**Level 2: Office** (e.g., Planning Office, Qualifications and Standards Office)
**Level 3: Division** (e.g., Policy Research Division, Competency Standards Division)

## ✅ Already Completed

### 1. Database & Entities
- ✅ Created `OfficeCluster` entity (`src/Entity/OfficeCluster.php`)
- ✅ Created `Division` entity (`src/Entity/Division.php`)
- ✅ Updated `Office` entity with cluster and divisions relationships
- ✅ Created `OfficeClusterRepository` (`src/Repository/OfficeClusterRepository.php`)
- ✅ Created `DivisionRepository` (`src/Repository/DivisionRepository.php`)
- ✅ Created and ran migration (`migrations/Version20260302_CreateClustersAndDivisions.php`)
- ✅ Database tables exist: `office_clusters`, `divisions`, `offices.cluster_id`

### 2. File Attachments & Zoom Links
- ✅ Meeting type and zoom link functionality working
- ✅ File attachments upload and save to database
- ✅ Public calendar displays meeting info and attachments

## 🎯 What Needs to Be Done Next

### Phase 1: Admin CRUD Controllers (ROLE_ADMIN only)

Create two admin controllers with full CRUD operations:

#### A. OfficeClusterController (`src/Controller/Admin/OfficeClusterController.php`)
**Routes:** `/admin/clusters`
**Actions:**
- `index()` - List all clusters with office count
- `new()` - Show create form
- `create()` - Handle form submission
- `edit($id)` - Show edit form
- `update($id)` - Handle update
- `delete($id)` - Delete cluster (with confirmation)

**Form Fields:**
- Name (text, required)
- Code (text, required, uppercase, unique)
- Description (textarea, optional)
- Color (color picker, optional)
- Display Order (number, default 0)
- Is Active (checkbox, default true)

#### B. DivisionController (`src/Controller/Admin/DivisionController.php`)
**Routes:** `/admin/divisions`
**Actions:**
- `index()` - List all divisions with office info
- `new()` - Show create form with office dropdown
- `create()` - Handle form submission
- `edit($id)` - Show edit form
- `update($id)` - Handle update
- `delete($id)` - Delete division

**Form Fields:**
- Name (text, required)
- Code (text, required, uppercase, unique)
- Office (dropdown, required - shows all offices)
- Description (textarea, optional)
- Display Order (number, default 0)
- Is Active (checkbox, default true)

### Phase 2: Admin Templates

#### A. Cluster Templates
**File:** `templates/admin/cluster/index.html.twig`
- Table with columns: Name, Code, Color, # Offices, Status, Actions
- "Create New Cluster" button
- Edit/Delete buttons per row
- Search and sort functionality

**File:** `templates/admin/cluster/form.html.twig`
- Shared form for create/edit
- Color picker input
- Validation messages
- Cancel/Save buttons

#### B. Division Templates
**File:** `templates/admin/division/index.html.twig`
- Table with columns: Name, Code, Office, Cluster, Status, Actions
- "Create New Division" button
- Edit/Delete buttons per row
- Filter by office/cluster

**File:** `templates/admin/division/form.html.twig`
- Shared form for create/edit
- Office dropdown (grouped by cluster)
- Validation messages
- Cancel/Save buttons

### Phase 3: Navigation Update

Update the main navigation sidebar to include admin menu (visible only to ROLE_ADMIN):

```twig
{% if is_granted('ROLE_ADMIN') %}
<div class="nav-section">
    <div class="nav-header">Administration</div>
    <a href="{{ path('admin_cluster_index') }}" class="nav-item">
        <svg>...</svg> Clusters
    </a>
    <a href="{{ path('admin_office_index') }}" class="nav-item">
        <svg>...</svg> Offices
    </a>
    <a href="{{ path('admin_division_index') }}" class="nav-item">
        <svg>...</svg> Divisions
    </a>
</div>
{% endif %}
```

### Phase 4: Update Legend Display

**Update HomeController** (`src/Controller/HomeController.php`):
```php
// Fetch clusters with offices and divisions
$clusters = $this->officeClusterRepository->findAllWithOffices();

return $this->render('home/index.html.twig', [
    'events' => $formattedEvents,
    'clusters' => $clusters, // Pass clusters instead of flat offices
]);
```

**Update Legend Template** (`templates/home/index.html.twig`):
Display 3-level hierarchy:
```
ODDG-PP
├── Planning Office (PO)
│   ├── Policy Research Division (PRED)
│   ├── Policy Planning Division (PPD)
│   └── ...
└── Qualifications Office (QSO)
    ├── Competency Standards Division (CSDD)
    └── ...
```

## Example Data Structure

```
Cluster: ODDG-PP
├── Office: Planning Office (PO) [Color: #FF5733]
│   ├── Division: Policy Research and Evaluation Division (PRED)
│   ├── Division: Policy and Planning Division (PPD)
│   ├── Division: Foreign Relations Division (FRPDD)
│   └── Division: Labor Market Information Division (LMID)
└── Office: Qualifications and Standards Office (QSO) [Color: #33FF57]
    ├── Division: Competency Standards Development Division (CSDD)
    └── Division: Curriculum Development Division (CTADD)
```

## Security Requirements

All admin routes MUST be protected:
```php
#[Route('/admin/clusters')]
#[IsGranted('ROLE_ADMIN')]
class OfficeClusterController extends AbstractController
```

## UI/UX Guidelines

1. **Tables**: Use DataTables for sorting/searching
2. **Forms**: Bootstrap styling with validation
3. **Colors**: Color picker for cluster colors
4. **Confirmations**: Modal dialogs for delete actions
5. **Flash Messages**: Success/error notifications
6. **Breadcrumbs**: Admin > Clusters > Edit Cluster
7. **Responsive**: Mobile-friendly tables

## Testing Checklist

After implementation:
- [ ] Admin can create clusters
- [ ] Admin can create divisions and assign to offices
- [ ] Admin can edit/delete clusters and divisions
- [ ] Non-admin users cannot access admin routes
- [ ] Legend displays 3-level hierarchy correctly
- [ ] Events still work with new structure
- [ ] Office colors display correctly in legend

## Files to Create/Modify

**New Files:**
- `src/Controller/Admin/OfficeClusterController.php`
- `src/Controller/Admin/DivisionController.php`
- `templates/admin/cluster/index.html.twig`
- `templates/admin/cluster/form.html.twig`
- `templates/admin/division/index.html.twig`
- `templates/admin/division/form.html.twig`

**Files to Modify:**
- `templates/base.html.twig` (add admin navigation)
- `src/Controller/HomeController.php` (fetch clusters)
- `templates/home/index.html.twig` (update legend display)

## Start New Conversation With:

"I need to continue implementing the Cluster-Office-Division hierarchy. The database entities and migration are complete. Now I need to:

1. Create OfficeClusterController with CRUD operations (admin only)
2. Create DivisionController with CRUD operations (admin only)
3. Create admin templates for managing clusters and divisions
4. Add admin navigation to the sidebar
5. Update the public calendar legend to show the 3-level hierarchy

The entities are in:
- src/Entity/OfficeCluster.php
- src/Entity/Division.php
- src/Entity/Office.php (updated with relationships)

The repositories are in:
- src/Repository/OfficeClusterRepository.php
- src/Repository/DivisionRepository.php

Please start with creating the OfficeClusterController."
