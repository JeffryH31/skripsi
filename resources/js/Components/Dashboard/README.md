# Dashboard Components

This directory contains all the refactored Dashboard components following Vue 3 Composition API best practices.

## Structure

```
Dashboard/
├── EmptyStates/       # Empty state components
│   ├── NoWorkspace.vue
│   └── NoBoard.vue
├── Sidebar/           # Sidebar navigation
│   └── SidebarContent.vue
├── Board/             # Main board components
│   ├── BoardHeader.vue
│   ├── FeatureCard.vue
│   └── KanbanBoard.vue
├── Feature/           # Feature modal & nested tasks
│   ├── TaskCard.vue
│   ├── TaskKanban.vue
│   └── FeatureModal.vue
├── Modals/            # All modal dialogs
│   ├── WorkspaceModal.vue
│   ├── BoardModal.vue
│   ├── DeleteConfirmModal.vue
│   ├── ActivityLogModal.vue
│   ├── TimeTrackingModal.vue
│   ├── MemberModal.vue
│   ├── LabelModal.vue
│   ├── AddTimeModal.vue
│   └── TaskEditModal.vue
└── index.js           # Barrel export
```

## Composables

Located in `resources/js/Composables/`:

- **useTimer.js** - Timer management for time tracking
- **useNotification.js** - Snackbar notification system
- **useFilters.js** - Search and filter logic
- **useActivity.js** - Activity logging and statistics

## Usage

Import components from the barrel export:

```javascript
import {
    NoWorkspace,
    NoBoard,
    SidebarContent,
    BoardHeader,
    KanbanBoard,
    FeatureModal
} from '@/Components/Dashboard';
```

Import composables:

```javascript
import { useTimer, useNotification, useFilters, useActivity } from '@/Composables';
```

## Benefits

1. **Maintainable** - Each component has a single responsibility
2. **Reusable** - Components can be used independently
3. **Testable** - Easy to write unit tests for each component
4. **Scalable** - Easy to add new features
5. **Type-safe** - All props are defined with types and defaults

## Original File

The original monolithic Dashboard.vue (3248 lines) has been backed up to:
- `resources/js/Pages/Dashboard.old.vue`

The new refactored Dashboard.vue is only ~727 lines, acting as an orchestrator for all the components.
