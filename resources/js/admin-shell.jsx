import '../css/app.css';
import { useEffect, useRef } from 'react';
import { createRoot } from 'react-dom/client';
import { ShellPropsProvider } from '@/context/ShellPropsContext';
import { AdminShellLayout } from '@/Layouts/AdminShellLayout';

function AdminShellApp({ shellProps }) {
    const contentRef = useRef(null);

    useEffect(() => {
        const bladeRoot = document.getElementById('admin-blade-content');

        if (!bladeRoot || !contentRef.current) {
            return;
        }

        while (bladeRoot.firstChild) {
            contentRef.current.appendChild(bladeRoot.firstChild);
        }

        bladeRoot.remove();
    }, []);

    return (
        <ShellPropsProvider value={shellProps}>
            <AdminShellLayout
                routeName={shellProps.routeName}
                contentRef={contentRef}
            />
        </ShellPropsProvider>
    );
}

const propsElement = document.getElementById('admin-shell-props');
const rootElement = document.getElementById('admin-shell-root');

if (propsElement && rootElement) {
    const shellProps = JSON.parse(propsElement.textContent);
    createRoot(rootElement).render(<AdminShellApp shellProps={shellProps} />);
}
