<style>
    /* 
     * Skope Tutor Premium UI Core
     * Unified Design Tokens — Blue Edition
     */
    :root {
        --primary: #00BFFF;
        --primary-dark: #0099CC;
        --secondary: #FF8C00;
        --success: #10B981;
        --danger: #ef4444;
        --warning: #F59E0B;
        --glass: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.4);
        --text-main: #0f172a;
        --text-dim: #64748b;
        --border: #f1f5f9;
        --radius-m: 16px;
        --radius-l: 24px;
        --radius-xl: 32px;
        --shadow-soft: 0 4px 20px rgba(0,0,0,0.04);
        --shadow-premium: 0 20px 40px rgba(0, 191, 255, 0.12);
        --grad-primary: linear-gradient(135deg, #00BFFF 0%, #0099CC 100%);
        --grad-premium: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    }

    body { 
        font-family: 'Inter', sans-serif; 
        background: #f8fafc; 
        color: var(--text-main); 
        -webkit-font-smoothing: antialiased;
        margin: 0;
    }

    .premium-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--radius-l);
        box-shadow: var(--shadow-soft);
        transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    .premium-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(0, 191, 255, 0.1);
        border-color: rgba(0, 191, 255, 0.2);
    }

    .btn-premium {
        background: var(--grad-primary);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 0.85rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 10px 25px rgba(0, 191, 255, 0.25);
        transition: 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        text-decoration: none;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    .btn-premium:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 15px 35px rgba(0, 191, 255, 0.35);
        filter: brightness(1.08);
    }

    .p-badge {
        padding: 6px 14px;
        background: #E0F7FF;
        color: var(--primary-dark);
        border-radius: 50px;
        font-size: 0.62rem;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        border: 1px solid rgba(0, 191, 255, 0.15);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-pill {
        padding: 6px 12px;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid transparent;
    }
    .status-pill.active { background: #ECFDF5; color: #10B981; border-color: rgba(16, 185, 129, 0.2); }
    .status-pill.pending { background: #FFFBEB; color: #F59E0B; border-color: rgba(245, 158, 11, 0.2); }
    .status-pill.danger { background: #FEF2F2; color: #EF4444; border-color: rgba(239, 68, 68, 0.2); }

    .action-btn-sm {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        border: 1px solid var(--border);
        color: var(--text-dim);
        transition: 0.2s;
        text-decoration: none;
    }
    .action-btn-sm:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: translateY(-2px);
    }

    .glass-effect {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #f8fafc; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--primary); }

    /* Responsive Grid Utilities */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
    .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }

    @media (max-width: 1200px) {
        .grid-4 { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 1024px) {
        .grid-3 { grid-template-columns: 1fr 1fr; }
        .grid-2, .grid-3, .grid-4 { gap: 24px; }
    }

    @media (max-width: 768px) {
        .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr !important; gap: 20px; }
    }

    /* Animations */
    @keyframes slideIn {
        from { transform: translateY(10px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
</style>
