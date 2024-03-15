import { createRoot } from 'react-dom/client';
import SourceConfig from './index.jsx';

/**
 * That function allow to render the componenton any time
 * and exists beacause Magento dont allow to load scripts
 * after the body is opened.
 *
 * @param {Array} srcList Merged common and advanced plans data.
 *
 * @todo Maybe convert srcList to object using this format:
 * {
 *    reference: {
 *      name: string,
 *      reference: string,
 *      installments: [
 *        uid: string,
 *        name: string,
 *        description: string,
 *        active: boolean,
 *        advanced: boolean,
 *      ]
 *    }
 * }
 */
export default function initSourceConfig(srcList) {
  createRoot(document.getElementById('installments-sorter')).render(
    <SourceConfig srcList={srcList} />
  );
}

// Make available to other scripts
window.initSourceConfig = initSourceConfig;
