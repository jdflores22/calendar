# Next Steps: Cluster-Office-Division Implementation

## ✅ Completed So Far

1. ✅ Created `OfficeCluster` entity
2. ✅ Created `Division` entity  
3. ✅ Updated `Office` entity with relationships
4. ✅ Created `OfficeClusterRepository`
5. ✅ Created `DivisionRepository`
6. ✅ Created and ran database migration
7. ✅ Database tables created:
   - `office_clusters`
   - `divisions`
   - `offices.cluster_id` column added

## 🔄 Next Steps

### 1. Create Admin Controllers (ROLE_ADMIN only)

**OfficeClusterController** - `/admin/clusters`
```php
- index() - List all clusters
- new() - Create form
- create() - Store new cluster
- edit() - Edit form
- update() - Update cluster
- delete() - Delete cluster
```

**DivisionController** - `/admin/divisions`
```php
- index() - List all divisions
- new() - Create form (with office dropdown)
- create() - Store new division
- edit() - Edit form
- update() - Update division
- delete() - Delete division
```

### 2. Update Navigation (Admin Only)

Add to left sidebar navigation (only visible to ROLE_ADMIN):
```
Settings (Dropdown)
├── Clusters
├── Offices
└── Divisions
```

### 3. Create Templates

**Cluster Management:**
- `templates/admin/cluster/index.html.twig` - List view with table
- `templates/admin/cluster/form.html.twig` - Create/Edit form

**Division Management:**
- `templates/admin/division/index.html.twig` - List view with table
- `templates/admin/division/form.html.twig` - Create/Edit form

### 4. Update Legend Display

**HomeController:**
```php
// Fetch clusters with offices and divisions
$clusters = $officeClusterRepository->findAllWithOffices();

// For each office, fetch divisions
foreach ($clusters as $cluster) {
    foreach ($cluster->getOffices() as $office) {
        $office->getDivisions(); // Already loaded via relationship
    }
}
```

**Legend Template Structure:**
```html
<div class="legend-panel">
    {% for cluster in clusters %}
    <div class="cluster-section">
        <h3>{{ cluster.name }} ({{ cluster.code }})</h3>
        {% for office in cluster.offices %}
        <div class="office-section">
            <div class="office-header">
                <span class="color-dot" style="background: {{ office.color }}"></span>
                {{ office.name }} ({{ office.code }})
            </div>
            {% if office.divisions|length > 0 %}
            <ul class="division-list">
                {% for division in office.divisions %}
                <li>{{ division.name }} ({{ division.code }})</li>
                {% endfor %}
            </ul>
            {% endif %}
        </div>
        {% endfor %}
    </div>
    {% endfor %}
</div>
```

### 5. Seed Initial Data (Optional)

Create a command to seed initial clusters:
```bash
php bin/console app:seed-clusters
```

Example clusters:
- OSEC (Office of the Secretary)
- ODDG-PP (Office of the Deputy Director-General for Policies and Planning)
- ODDG-AI (Office of the Deputy Director-General for Admin and Infrastructure)
- ODDG-SC (Office of the Deputy Director-General for Sectoral Concerns)
- ODDG-TESDO (Office of the Deputy Director-General for TESDO)

## File Structure

```
src/
├── Controller/
│   └── Admin/
│       ├── OfficeClusterController.php (NEW)
│       └── DivisionController.php (NEW)
├── Entity/
│   ├── OfficeCluster.php ✅
│   ├── Division.php ✅
│   └── Office.php ✅ (updated)
└── Repository/
    ├── OfficeClusterRepository.php ✅
    └── DivisionRepository.php ✅

templates/
└── admin/
    ├── cluster/
    │   ├── index.html.twig (NEW)
    │   └── form.html.twig (NEW)
    └── division/
        ├── index.html.twig (NEW)
        └── form.html.twig (NEW)

migrations/
└── Version20260302_CreateClustersAndDivisions.php ✅
```

## Commands to Continue

1. Create controllers:
```bash
php bin/console make:controller Admin/OfficeClusterController
php bin/console make:controller Admin/DivisionController
```

2. Test database:
```bash
php bin/console doctrine:schema:validate
```

3. Create seed command (optional):
```bash
php bin/console make:command app:seed-clusters
```

## Security

All cluster and division management routes must be protected:
```php
#[IsGranted('ROLE_ADMIN')]
class OfficeClusterController extends AbstractController
{
    // ...
}
```

## UI/UX Notes

- Use DataTables for list views (sortable, searchable)
- Add drag-and-drop for reordering display_order
- Color picker for cluster colors
- Breadcrumbs: Admin > Clusters > Edit
- Success/error flash messages
- Confirmation modals for delete actions

Would you like me to continue with creating the controllers and templates?
