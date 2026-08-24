import {AlertCircle} from 'lucide-react';
import {Button} from 'antd';
import CollectionLoader from '../../components/CollectionLoader.jsx';

/**
 * Standard tab content wrapper for SoD collection pages (matches vehicle-management tabs).
 */
const CollectionTabShell = ({
    error,
    onRetry,
    children,
    loading = false,
    empty = false,
    showErrorBanner,
}) => {
    const showBanner =
        showErrorBanner !== undefined
            ? showErrorBanner
            : Boolean(error) && empty && !loading;

    return (
        <div className="space-y-4">
            {showBanner && (
                <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div className="flex">
                            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
                            <div className="ml-3">
                                <h3 className="text-sm font-medium text-red-800">Error</h3>
                                <p className="mt-1 text-sm text-red-700">{error}</p>
                            </div>
                        </div>
                        {onRetry ? (
                            <Button size="small" onClick={onRetry}>
                                Retry
                            </Button>
                        ) : null}
                    </div>
                </div>
            )}

            <div className="rounded-lg bg-white shadow">
                {loading ? (
                    <CollectionLoader />
                ) : (
                    children
                )}
            </div>
        </div>
    );
};

export default CollectionTabShell;
