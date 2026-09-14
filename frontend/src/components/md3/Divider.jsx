import React from 'react';

function Divider({ className = '' }) {
    return <hr className={`md-divider ${className}`.trim()} />;
}

export default Divider;
