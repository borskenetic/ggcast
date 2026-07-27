import { createContext, useContext } from 'react';

export const ShellPropsContext = createContext(null);

export function ShellPropsProvider({ value, children }) {
    return (
        <ShellPropsContext.Provider value={value}>{children}</ShellPropsContext.Provider>
    );
}

export function useShellProps() {
    const context = useContext(ShellPropsContext);

    if (!context) {
        throw new Error('useShellProps must be used within ShellPropsProvider');
    }

    return context;
}
