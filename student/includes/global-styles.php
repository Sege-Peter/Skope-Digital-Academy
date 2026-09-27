<style>
    /* 
     * Skope Premium UI Core
     * Unified Design Tokens for all student portal modules
     */
    :root {
        --primary: #00AEEF;
        --primary-dark: #0072FF;
        --secondary: #FF5A1F;
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
        --shadow-premium: 0 20px 40px rgba(0, 0, 0, 0.08);
        --grad-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        --grad-premium: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    }

    body { 
        font-family: 'Inter', sans-serif; 
        background: #fdfdfd; 
        color: var(--text-main); 
        -webkit-font-smoothing: antialiased;
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
        transform: translateY(-8px);
        box-shadow: var(--shadow-premium);
        border-color: rgba(0, 174, 239, 0.2);
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
        box-shadow: 0 10px 25px rgba(0, 174, 239, 0.25);
        transition: 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        text-decoration: none;
        letter-spacing: 0.5px;
    }
    .btn-premium:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 15px 35px rgba(0, 174, 239, 0.35);
        filter: brightness(1.1);
    }

    .btn-action {
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 0.75rem;
        transition: 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .p-badge {
        padding: 6px 14px;
        background: #F0F9FF;
        color: var(--primary);
        border-radius: 50px;
        font-size: 0.62rem;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        border: 1px solid rgba(0, 174, 239, 0.1);
        display: inline-block;
    }

    .glass-effect {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .shimmer {
        background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
        background-size: 200% 100%;
        animation: shimmerEffect 2s infinite;
    }
    @keyframes shimmerEffect { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    /* Custom Scrollbar for Premium Feel */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>

