# LabFlow

An automated laboratory apparatus tracking and asset circulation system engineered to replace manual paper logging with physical verification, real-time inventory management, and barcode/QR verification workflows.

---

## Architectural Overview

LabFlow is built to handle laboratory custody workflows, asset lifecycles, and stockroom circulation across administrative and departmental tiers. The system enforces verifiable equipment checkouts and check-ins, tracks equipment damage and repairs, and provides a centralized interface for both lab custodians and borrowers.

### Key Capabilities

- **Equipment Circulation Engine:** Complete check-out and check-in lifecycle for chemical apparatus, glassware, measuring tools, and specialized lab devices.
- **Physical Verification & Audio Feedback:** Scanner-assisted identification using audio hooks (`scan_su.wav`, `scan_f.wav`) to deliver immediate acoustic confirmation on checkout validations or scanning faults.
- **Role-Segmented Control Surfaces:** Dedicated views for administrators (`AdminDemo/`, `BorrowManagement.html`), staff custodians (`stock_room.php`, `operation.php`), and borrowers.
- **Integrated Assistance Layer:** Direct backend-supported real-time chat service (`chat_api.php`) for operational troubleshooting and stockroom inquiries.
- **Asset Registry & Catalog:** Structured visual inventory indexing cataloged laboratory assets with serialized tracking tokens.

---

## Tech Stack

- **Backend Runtime:** PHP
- **Frontend / UI:** HTML5, CSS3, Tailwind CSS (compiled via PostCSS into `output.css`), Vanilla JavaScript
- **Audio / Media:** Web Audio API hooks (WAV pipeline)
- **Tooling & Environments:** Live Server configuration (`.vscode/settings.json`), PostCSS/CLI compiler

---

## Repository Layout

```text
├── HTML_Demo/
│   ├── AdminDemo/
│   │   └── BorrowManagement.html # Admin dashboard for request queues & releases
│   ├── css/
│   │   └── output.css            # Compiled Tailwind utility classes
│   ├── img/                      # Branding, UI assets, and institution vectors
│   ├── 404.html                  # Missing route handler
│   ├── chat_api.php              # Real-time message ingest & custodian dispatch
│   ├── landing.html              # Public-facing system overview and portal entry
│   ├── list_models.php           # Model registry and metadata serialization
│   ├── operation.php             # Core operational handlers (borrow, return, audit)
│   ├── registered.html           # User registration state view
│   ├── stock_room.php            # Active inventory levels and stock management
│   └── why.html                  # Institutional documentation and rationale
├── assets/
│   ├── audio/
│   │   ├── scan_f.wav            # Scanner fault audio trigger
│   │   └── scan_su.wav           # Scanner success audio trigger
│   ├── css/
│   │   └── style.css             # Base stylesheet definitions
│   └── img/
│       └── items/                # Serialized equipment item image directory
├── adminhome.php                 # Administrative routing hub
└── .vscode/
    └── settings.json             # Dev server bindings and local port mappings
