# Office Cluster Implementation Plan

## Overview
Implement a hierarchical organization system where offices are grouped into clusters for better organization and legend display.

## Database Structure

### New Table: `office_clusters`
```sql
CREATE TABLE office_clusters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    color VARCHAR(7),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
```

### Update Table: `offices`
```sql
ALTER TABLE offices 
ADD COLUMN cluster_id INT NULL,
ADD FOREIGN KEY (cluster_id) REFERENCES office_clusters(id) ON DELETE SET NULL;
```

## Implementation Steps

### 1. Create Entity: OfficeCluster
**File:** `src/Entity/OfficeCluster.php`
- Properties: id, name, code, description, color, displayOrder, isActive, createdAt, updatedAt
- Relationship: OneToMany with Office
- Methods: getters, setters, addOffice, removeOffice

### 2. Update Entity: Office
**File:** `src/Entity/Office.php`
- Add property: cluster (ManyToOne relationship)
- Add methods: getCluster(), setCluster()

### 3. Create Repository: OfficeClusterRepository
**File:** `src/Repository/OfficeClusterRepository.php`
- findAllActive()
- findByCode()
- findWithOffices()

### 4. Create Migration
**File:** `migrations/VersionXXXX_CreateOfficeClusters.php`
- Create office_clusters table
- Add cluster_id to offices table
- Optionally seed default clusters

### 5. Create Controller: OfficeClusterController
**File:** `src/Controller/OfficeClusterController.php`
**Routes:**
- GET /admin/clusters - List all clusters
- GET /admin/clusters/new - Create form
- POST /admin/clusters - Store new cluster
- GET /admin/clusters/{id}/edit - Edit form
- PUT /admin/clusters/{id} - Update cluster
- DELETE /admin/clusters/{id} - Delete cluster

### 6. Create Templates
**Files:**
- `templates/office_cluster/index.html.twig` - List view
- `templates/office_cluster/new.html.twig` - Create form
- `templates/office_cluster/edit.html.twig` - Edit form
- `templates/office_cluster/_form.html.twig` - Shared form

### 7. Update Office Management
**File:** `templates/office/new.html.twig` & `edit.html.twig`
- Add cluster dropdown selection
- Allow assigning office to a cluster

### 8. Update Legend Display
**Files:**
- `templates/home/index.html.twig` - Public calendar legend
- `templates/calendar/index.html.twig` - Authenticated calendar legend

**Display Structure:**
```
Legend
├── Cluster 1 (OSEC)
│   ├── Office of the Secretary
│   └── Public Information Office
├── Cluster 2 (ORODC-PP)
│   ├── Planning Division
│   └── Policy Development
└── Cluster 3 (ORODC-AI)
    ├── Regional Office NCR
    └── Provincial Office - Batangas
```

### 9. Update HomeController
**File:** `src/Controller/HomeController.php`
- Fetch clusters with their offices
- Pass to template grouped by cluster

## UI/UX Design

### Legend Panel Layout
```
┌─────────────────────────────────────────┐
│ Legend                            [×]   │
├─────────────────────────────────────────┤
│ ┌─ OSEC ──────────────────────────────┐ │
│ │ ● Office of the Secretary (OSEC)   │ │
│ │ ● Public Information Office (PIO)  │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─ ORODC-PP ──────────────────────────┐ │
│ │ ● Planning Division (PPDD)         │ │
│ │ ● Policy Development (PD)          │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

### Admin Cluster Management
- Table with columns: Name, Code, # of Offices, Status, Actions
- Create/Edit form with: Name, Code, Description, Color, Display Order
- Ability to reorder clusters (drag & drop or up/down buttons)
- Assign offices to clusters from office edit page

## Benefits
1. **Better Organization**: Logical grouping of offices
2. **Cleaner Legend**: Collapsible clusters reduce visual clutter
3. **Flexibility**: Admin can reorganize as needed
4. **Scalability**: Easy to add new offices to existing clusters

## Migration Strategy
1. Create clusters table and add cluster_id to offices
2. Create default clusters based on existing office naming patterns
3. Auto-assign offices to clusters based on name patterns
4. Allow admin to adjust assignments

## Next Steps
Would you like me to:
1. Start with the database entities and migration?
2. Create the full CRUD for cluster management?
3. Update the legend display to show clusters?

Let me know and I'll begin implementation!
